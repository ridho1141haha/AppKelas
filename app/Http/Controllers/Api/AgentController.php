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
        $rawMessage = $request->input('message') ?? $request->post('message') ?? 'Halo';
        $message = trim((string)$rawMessage);
        
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return response()->json(['success' => false, 'reply' => '❌ API Key Gemini Missing di Railway.']);
        }

        try {
            // 🛠️ Definisi Tools (Function Calling)
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

            // 📜 Setup History & System Instruction (Diselipkan di user message pertama untuk stabilitas)
            $today = now()->translatedFormat('l, d F Y');
            $promptContext = "Instruksi Sistem: Kamu adalah 'Asisten Kelas' yang ramah. Hari ini $today. Jawab dengan Bahasa Indonesia santai. Kamu punya akses ke database tugas/jadwal.\n\nPesan User: " . $message;

            $history = [
                [
                    'role' => 'user',
                    'parts' => [['text' => $promptContext]]
                ]
            ];

            $maxIterations = 5;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'tools' => [['function_declarations' => $tools['function_declarations']]]
                ];

                // GUNAKAN GEMINI 2.0 FLASH (Lebih Stabil daripada 2.5 untuk Free Tier)
                $response = Http::timeout(40)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    $err = $response->json();
                    Log::error('Gemini API Error', $err);
                    return response()->json([
                        'success' => false, 
                        'reply' => '❌ AI lagi sibuk (Error ' . $response->status() . '). Coba tanya lagi bentar lagi ya!',
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
                                    'response' => ['result' => $result]
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

            return response()->json(['success' => false, 'reply' => '❌ AI lagi bingung, coba ketik ulang pertanyaannya.']);

        } catch (\Exception $e) {
            Log::error('Fatal Chat Error', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => '❌ Sistem Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': 
                    return Task::select('title', 'subject', 'deadline')->get()->take(5)->toArray();
                case 'get_schedules': 
                    return Schedule::all()->take(5)->toArray();
                case 'add_task': 
                    $task = Task::create($args);
                    return "Berhasil menambahkan tugas: " . $task->title;
                default: 
                    return "Fungsi tidak ditemukan.";
            }
        } catch (\Exception $e) {
            return "Error akses data: " . $e->getMessage();
        }
    }
}
