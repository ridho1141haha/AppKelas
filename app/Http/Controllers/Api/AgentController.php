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
        // Deteksi input message
        $rawMessage = $request->input('message') ?? $request->post('message') ?? 'Halo';
        $message = trim((string)$rawMessage);
        
        if (empty($message)) {
            $message = 'Halo';
        }

        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false, 
                'reply' => '❌ API Key Gemini belum dikonfigurasi di Railway.',
            ], 500);
        }

        try {
            // Definisi Tools
            $tools = [
                'function_declarations' => [
                    [
                        'name' => 'get_tasks', 
                        'description' => 'Melihat daftar tugas sekolah atau PR yang belum selesai.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'get_schedules', 
                        'description' => 'Melihat jadwal pelajaran sekolah.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'add_task',
                        'description' => 'Menambah tugas baru ke database.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'subject' => ['type' => 'string'],
                                'deadline' => ['type' => 'string'],
                                'description' => ['type' => 'string']
                            ],
                            'required' => ['title', 'subject', 'deadline']
                        ]
                    ]
                ]
            ];

            // Setup History
            $history = [
                [
                    'role' => 'user',
                    'parts' => [['text' => $message]]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstructionText = "Kamu adalah 'Asisten Kelas' yang ramah. Hari ini $today. Gunakan tools untuk melihat tugas/jadwal. Jawablah dengan bahasa Indonesia yang santai dan informatif.";

            $maxIterations = 5;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstructionText]]
                    ],
                    'tools' => [$tools]
                ];

                // Pake v1beta dan model Pro sebagai alternatif yang lebih kuat
                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    $error = $response->json();
                    Log::error('Gemini API Error', ['status' => $response->status(), 'error' => $error]);
                    
                    // Jika Pro juga NOT_FOUND, coba balik ke Flash tapi tanpa system_instruction
                    if ($response->status() === 404) {
                         unset($payload['system_instruction']);
                         // Masukkan instruksi ke dalam pesan user pertama sebagai fallback
                         $history[0]['parts'][0]['text'] = $systemInstructionText . "\n\nUser: " . $message;
                         $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", $payload);
                         if (!$response->successful()) {
                             $error = $response->json();
                             return response()->json(['success' => false, 'reply' => '❌ Google AI Error: ' . ($error['error']['message'] ?? 'Unknown'), 'debug' => $error], 500);
                         }
                    } else {
                        return response()->json([
                            'success' => false, 
                            'reply' => '❌ Google AI Error (' . $response->status() . '): ' . ($error['error']['message'] ?? 'Service Unavailable'),
                            'debug' => $error
                        ], 500);
                    }
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                
                if (!$content) {
                    return response()->json(['success' => false, 'reply' => '❌ AI tidak memberikan respon.', 'raw' => $resData], 500);
                }

                $history[] = $content;
                $part = $content['parts'][0] ?? null;

                if (isset($part['functionCall'])) {
                    $name = $part['functionCall']['name'];
                    $args = $part['functionCall']['args'] ?? [];
                    
                    $result = $this->executeLocalFunction($name, $args);
                    
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
                    continue;
                } 
                
                if (isset($part['text'])) {
                    return response()->json(['success' => true, 'reply' => $part['text']]);
                }

                break;
            }

            return response()->json(['success' => false, 'reply' => '❌ Sesi chat terlalu panjang.']);

        } catch (\Exception $e) {
            Log::error('Chat Fatal Error', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => '❌ Sistem Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': 
                    $tasks = Task::select('title', 'subject', 'deadline')->where('status', '!=', 'completed')->get();
                    return $tasks->isEmpty() ? "Tidak ada tugas yang perlu dikerjakan." : $tasks->toArray();
                case 'get_schedules': 
                    return Schedule::select('day', 'subjects', 'dismissal_time')->get()->toArray();
                case 'add_task': 
                    $task = Task::create([
                        'title' => $args['title'] ?? 'Tugas Baru',
                        'subject' => $args['subject'] ?? 'Umum',
                        'deadline' => $args['deadline'] ?? now()->format('Y-m-d'),
                        'description' => $args['description'] ?? '',
                        'status' => 'pending'
                    ]);
                    return "Tugas '" . $task->title . "' berhasil ditambahkan.";
                default: 
                    return "Fungsi tidak ditemukan.";
            }
        } catch (\Exception $e) {
            return "Gagal akses database: " . $e->getMessage();
        }
    }
}
