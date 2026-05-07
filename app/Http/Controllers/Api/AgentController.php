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
        // Ambil pesan dari input 'message'. Cek di berbagai kemungkinan lokasi input.
        $message = $request->input('message') ?? $request->post('message') ?? 'Halo';
        
        Log::info('Chat Process Started', ['incoming_message' => $message]);

        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false, 
                'reply' => 'Konfigurasi Error: API Key Gemini belum diset di server.',
            ], 500);
        }

        try {
            // Definisi Tools agar AI bisa akses database
            $tools = [
                'function_declarations' => [
                    [
                        'name' => 'get_tasks', 
                        'description' => 'Ambil daftar tugas sekolah atau PR.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'get_schedules', 
                        'description' => 'Ambil jadwal pelajaran sekolah.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'add_task',
                        'description' => 'Menambahkan tugas baru ke dalam daftar.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string', 'description' => 'Judul tugas'],
                                'subject' => ['type' => 'string', 'description' => 'Mata pelajaran'],
                                'deadline' => ['type' => 'string', 'description' => 'Tanggal (YYYY-MM-DD)'],
                                'description' => ['type' => 'string', 'description' => 'Detail tambahan']
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
                    'parts' => [['text' => (string)$message]]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas' yang cerdas dan ramah. Hari ini adalah $today. Jawablah dengan bahasa Indonesia yang santai. Kamu punya akses ke database tugas dan jadwal sekolah melalui fungsi yang tersedia.";

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
                    $error = $response->json();
                    Log::error('Gemini API Error', ['detail' => $error, 'payload' => $payload]);
                    return response()->json([
                        'success' => false, 
                        'reply' => 'Maaf, otak AI-ku lagi konslet (Error 400/500).',
                        'debug' => $error
                    ], 500);
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                
                if (!$content) {
                    return response()->json(['success' => false, 'reply' => 'AI tidak memberikan jawaban.']);
                }

                $history[] = $content;
                $part = $content['parts'][0] ?? null;

                // Jika AI ingin memanggil fungsi (Tool Use)
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
                                    'response' => ['name' => $name, 'content' => $result]
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

            return response()->json(['success' => false, 'reply' => 'Wah, aku bingung mau jawab apa.']);

        } catch (\Exception $e) {
            Log::error('Chat Exception', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => 'Error Sistem: ' . $e->getMessage()], 500);
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
                    return ['status' => 'success', 'task' => $task->title];
                default: 
                    return ['error' => 'Fungsi tidak dikenal'];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
