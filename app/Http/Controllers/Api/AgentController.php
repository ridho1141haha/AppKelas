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
        // Handle input (JSON atau Form)
        $message = $request->input('message') ?? $request->post('message') ?? 'Halo';
        
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return response()->json(['success' => false, 'reply' => '❌ API Key Gemini Missing di Railway.']);
        }

        try {
            // 🛠️ Definisi Tools
            $tools = [
                'function_declarations' => [
                    [
                        'name' => 'get_tasks', 
                        'description' => 'Melihat daftar tugas sekolah atau PR.', 
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
                                'deadline' => ['type' => 'string', 'description' => 'Format YYYY-MM-DD'],
                                'description' => ['type' => 'string']
                            ],
                            'required' => ['title', 'subject', 'deadline']
                        ]
                    ]
                ]
            ];

            // 📜 Setup History & System Instruction
            $today = now()->translatedFormat('l, d F Y');
            $systemPrompt = "Kamu adalah 'Asisten Kelas'. Hari ini $today. Jawab dengan bahasa Indonesia santai. Gunakan tools untuk cek tugas atau jadwal.";

            $history = [
                [
                    'role' => 'user',
                    'parts' => [['text' => $systemPrompt . "\n\nPesan User: " . $message]]
                ]
            ];

            $maxIterations = 3;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'tools' => [['function_declarations' => $tools['function_declarations']]]
                ];

                // Pake v1beta yang lu pake buat list models tadi
                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    $err = $response->json();
                    return response()->json([
                        'success' => false, 
                        'reply' => '❌ Google API Error (' . $response->status() . '): ' . ($err['error']['message'] ?? 'Service Busy'),
                        'debug_detail' => $err
                    ], 500);
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                
                if (!$content) break;

                $history[] = $content;
                $part = $content['parts'][0] ?? null;

                // Cek Call Function
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
                                    'response' => ['content' => $result]
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

            return response()->json(['success' => false, 'reply' => '❌ Maaf, proses terlalu panjang.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'reply' => '❌ Sistem Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': 
                    return Task::all()->take(5)->toArray();
                case 'get_schedules': 
                    return Schedule::all()->take(5)->toArray();
                case 'add_task': 
                    Task::create($args);
                    return "Tugas berhasil ditambahkan ke database!";
                default: 
                    return "Fungsi tidak ditemukan.";
            }
        } catch (\Exception $e) {
            return "Error DB: " . $e->getMessage();
        }
    }
}
