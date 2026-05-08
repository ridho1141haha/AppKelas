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
        $rawMessage = $request->input('message') ?? $request->post('message') ?? 'Halo';
        $message = trim((string)$rawMessage);
        
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return response()->json(['success' => false, 'reply' => '❌ API Key Gemini Missing.']);
        }

        try {
            $tools = [
                'function_declarations' => [
                    [
                        'name' => 'get_tasks', 
                        'description' => 'Melihat daftar tugas sekolah.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'get_schedules', 
                        'description' => 'Melihat jadwal pelajaran.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'add_task',
                        'description' => 'Menambah tugas baru.',
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

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas'. Hari ini $today. Jawab dengan Bahasa Indonesia santai. Gunakan tools untuk akses data.";

            $history = [
                [
                    'role' => 'user',
                    'parts' => [['text' => $message]]
                ]
            ];

            $maxIterations = 3; // Kurangi iterasi buat hemat quota
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]]
                    ],
                    'tools' => [['function_declarations' => $tools['function_declarations']]]
                ];

                // Pake gemini-flash-latest yang lebih generic (biasanya lebih stabil quotanya)
                $response = Http::timeout(45)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    $err = $response->json();
                    if ($response->status() === 429) {
                        return response()->json([
                            'success' => false, 
                            'reply' => '⚠️ Waduh, si AI lagi capek (Quota Abis). Coba tunggu 1 menit terus chat lagi ya bos!',
                        ], 429);
                    }
                    Log::error('Gemini Error', $err);
                    return response()->json([
                        'success' => false, 
                        'reply' => '❌ Error ' . $response->status() . ': ' . ($err['error']['message'] ?? 'Unknown'),
                    ], 500);
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                
                if (!$content) break;

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

            return response()->json(['success' => false, 'reply' => '❌ Maaf, asisten gagal memproses permintaanmu.']);

        } catch (\Exception $e) {
            Log::error('Fatal Chat Error', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => '❌ Sistem Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': return Task::select('title', 'subject', 'deadline')->get()->take(5)->toArray();
                case 'get_schedules': return Schedule::all()->take(5)->toArray();
                case 'add_task': return Task::create($args) ? "Tugas berhasil ditambah." : "Gagal tambah tugas.";
                default: return "Fungsi tidak ada.";
            }
        } catch (\Exception $e) {
            return "Error DB: " . $e->getMessage();
        }
    }
}
