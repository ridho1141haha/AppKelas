<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\Schedule;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        // Deteksi input message dari berbagai kemungkinan sumber (JSON atau Form Data)
        $message = $request->input('message') ?? $request->post('message') ?? 'Halo';
        
        Log::info('Chat Process Started', ['incoming_message' => $message]);

        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false, 
                'reply' => '❌ API Key Gemini belum diset di server. Cek dashboard Railway.',
            ], 500);
        }

        try {
            // Definisi Tools (Function Calling)
            $tools = [
                'function_declarations' => [
                    [
                        'name' => 'get_tasks', 
                        'description' => 'Ambil semua daftar tugas sekolah atau PR.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'get_schedules', 
                        'description' => 'Ambil jadwal pelajaran sekolah.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'add_task',
                        'description' => 'Menambahkan data tugas baru ke dalam daftar tugas.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string', 'description' => 'Judul tugas'],
                                'subject' => ['type' => 'string', 'description' => 'Mata pelajaran'],
                                'deadline' => ['type' => 'string', 'description' => 'Tanggal deadline (YYYY-MM-DD)'],
                                'description' => ['type' => 'string', 'description' => 'Detail tugas']
                            ],
                            'required' => ['title', 'subject', 'deadline']
                        ]
                    ]
                ]
            ];

            // Inisialisasi History dengan pesan user
            $history = [
                [
                    'role' => 'user',
                    'parts' => [['text' => (string)$message]]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas' yang ramah. Hari ini adalah $today. Gunakan tools untuk melihat tugas, jadwal, atau menambah tugas baru. Jawablah dengan bahasa Indonesia yang santai.";

            $maxIterations = 5;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]]
                    ],
                    'tools' => [$tools]
                ];

                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    $errorData = $response->json();
                    Log::error('Gemini API Failure', ['status' => $response->status(), 'error' => $errorData]);
                    
                    return response()->json([
                        'success' => false, 
                        'reply' => '❌ Google AI Error (' . $response->status() . '): ' . ($errorData['error']['message'] ?? 'Unknown Error'),
                        'debug' => $errorData
                    ], 500);
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                
                if (!$content) {
                    return response()->json(['success' => false, 'reply' => '❌ Asisten tidak memberikan respon (Empty Content).']);
                }

                // Tambahkan pesan model ke history
                $history[] = $content;

                $part = $content['parts'][0] ?? null;

                // Cek apakah AI memanggil fungsi
                if (isset($part['functionCall'])) {
                    $name = $part['functionCall']['name'];
                    $args = $part['functionCall']['args'] ?? [];
                    
                    // Eksekusi fungsi lokal
                    $result = $this->executeLocalFunction($name, $args);
                    
                    // Tambahkan hasil fungsi ke history dengan role 'function'
                    $history[] = [
                        'role' => 'function', 
                        'parts' => [
                            [
                                'functionResponse' => [
                                    'name' => $name,
                                    'response' => [
                                        'content' => $result
                                    ]
                                ]
                            ]
                        ]
                    ];
                    // Lanjut iterasi biar AI memproses hasil fungsi
                    continue;
                } 
                
                // Jika AI memberikan teks balasan
                if (isset($part['text'])) {
                    return response()->json([
                        'success' => true, 
                        'reply' => $part['text']
                    ]);
                }

                break;
            }

            return response()->json(['success' => false, 'reply' => '❌ Maaf, aku terlalu lama berpikir. Coba tanya lagi ya!']);

        } catch (\Exception $e) {
            Log::error('Internal Chat Error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false, 
                'reply' => '❌ System Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
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
