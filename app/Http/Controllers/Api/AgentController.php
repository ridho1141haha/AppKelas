<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\Schedule;
use App\Models\Material;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        Log::info('Chat endpoint hit', ['request' => $request->all()]);
        
        $message = $request->input('message');
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false, 
                'reply' => 'Gagal: API Key Gemini belum terbaca di Railway (Cek Variables).',
            ], 500);
        }

        try {
            // Struktur Tools (Function Calling)
            $tools = [
                'function_declarations' => [
                    [
                        'name' => 'get_tasks', 
                        'description' => 'Ambil semua daftar tugas sekolah atau PR yang ada.', 
                        'parameters' => [
                            'type' => 'object', 
                            'properties' => (object)[]
                        ]
                    ],
                    [
                        'name' => 'get_schedules', 
                        'description' => 'Ambil semua jadwal pelajaran sekolah.', 
                        'parameters' => [
                            'type' => 'object', 
                            'properties' => (object)[]
                        ]
                    ],
                    [
                        'name' => 'add_task',
                        'description' => 'Menambahkan data tugas baru ke database.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string', 'description' => 'Judul tugas'],
                                'subject' => ['type' => 'string', 'description' => 'Mata pelajaran'],
                                'deadline' => ['type' => 'string', 'description' => 'Tanggal pengumpulan (YYYY-MM-DD)'],
                                'description' => ['type' => 'string', 'description' => 'Detail tugas']
                            ],
                            'required' => ['title', 'subject', 'deadline']
                        ]
                    ]
                ]
            ];

            // Inisialisasi History dengan pesan user pertama
            $history = [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $message]
                    ]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas' yang ramah. Hari ini adalah $today. Kamu bisa membantu melihat tugas, jadwal, atau menambah tugas baru menggunakan tools yang tersedia. Jawablah dengan bahasa Indonesia yang santai.";

            $maxIterations = 5;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'system_instruction' => [
                        'parts' => [
                            ['text' => $systemInstruction]
                        ]
                    ],
                    'tools' => [$tools]
                ];

                Log::debug('Gemini Payload', $payload);

                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    if ($response->status() === 429) {
                        return response()->json(['success' => false, 'reply' => '⏳ Jatah nanya ke AI lagi penuh, coba 1 menit lagi ya!'], 429);
                    }
                    $errorBody = $response->body();
                    Log::error('Gemini API Error', ['status' => $response->status(), 'body' => $errorBody, 'payload' => $payload]);
                    return response()->json([
                        'success' => false, 
                        'reply' => 'Google AI Error (' . $response->status() . ')',
                        'error_detail' => json_decode($errorBody, true) ?: $errorBody,
                        'debug_payload' => $payload
                    ], 500);
                }

                $resData = $response->json();
                $candidate = $resData['candidates'][0] ?? null;
                $content = $candidate['content'] ?? null;
                
                if (!$content) {
                    return response()->json(['success' => false, 'reply' => 'Maaf, AI tidak memberikan respon.', 'raw' => $resData], 500);
                }

                // Tambahkan respon AI ke history
                $history[] = $content;

                $part = $content['parts'][0] ?? null;

                if (isset($part['functionCall'])) {
                    $name = $part['functionCall']['name'];
                    $args = $part['functionCall']['args'] ?? [];
                    
                    // Eksekusi fungsi lokal
                    $result = $this->executeLocalFunction($name, $args);
                    
                    // Tambahkan hasil fungsi ke history agar AI bisa memprosesnya
                    $history[] = [
                        'role' => 'model', // Respon fungsi harus dikaitkan dengan role model/function tergantung versi, tapi biasanya model -> user flow
                        'parts' => [
                            [
                                'functionResponse' => [
                                    'name' => $name,
                                    'response' => [
                                        'name' => $name,
                                        'content' => $result
                                    ]
                                ]
                            ]
                        ]
                    ];
                    // Lanjut iterasi untuk membiarkan AI menjawab berdasarkan hasil fungsi
                    continue;
                } 
                
                if (isset($part['text'])) {
                    return response()->json(['success' => true, 'reply' => $part['text']]);
                }

                break;
            }

            return response()->json(['success' => false, 'reply' => 'Maaf, asisten terlalu lama berpikir.'], 500);

        } catch (\Exception $e) {
            Log::error('Chat Exception', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false, 
                'reply' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': 
                    return Task::all()->take(10)->toArray();
                case 'get_schedules': 
                    return Schedule::all()->take(10)->toArray();
                case 'add_task': 
                    $task = Task::create($args);
                    return ['status' => 'success', 'data' => $task->toArray()];
                default: 
                    return ['error' => 'Fungsi tidak ditemukan'];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
