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
            return response()->json(['success' => false, 'reply' => 'Gagal: API Key Gemini belum terbaca di Railway (Cek Variables).'], 500);
        }

        try {
            $tools = [
                [
                    'function_declarations' => [
                        ['name' => 'get_tasks', 'description' => 'Ambil daftar tugas sekolah.', 'parameters' => ['type' => 'object', 'properties' => (object)[]]],
                        ['name' => 'get_schedules', 'description' => 'Ambil jadwal pelajaran.', 'parameters' => ['type' => 'object', 'properties' => (object)[]]],
                        [
                            'name' => 'add_task',
                            'description' => 'Tambah tugas baru.',
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
                ]
            ];

            $history = [
                ['role' => 'user', 'parts' => [['text' => $message]]]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas' yang cerdas. Hari ini $today. Gunakan fungsi yang tersedia untuk membantu user.";

            $maxIterations = 5;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                    'contents' => $history,
                    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                    'tools' => $tools
                ]);

                if (!$response->successful()) {
                    $errorBody = substr($response->body(), 0, 500);
                    Log::error('Gemini Error', ['status' => $response->status(), 'body' => $errorBody]);
                    return response()->json([
                        'success' => false, 
                        'reply' => 'Google AI Error (' . $response->status() . '): ' . $errorBody
                    ], 500);
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                $part = $content['parts'][0] ?? null;

                if (!$content) break;

                // Masukin jawaban model ke history
                $history[] = $content;

                if (isset($part['functionCall'])) {
                    $name = $part['functionCall']['name'];
                    $args = $part['functionCall']['args'] ?? [];
                    
                    $result = $this->executeLocalFunction($name, $args);

                    // Masukin hasil fungsi ke history
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
                } else {
                    return response()->json([
                        'success' => true,
                        'reply' => $part['text'] ?? 'Aku bingung mau jawab apa.'
                    ]);
                }
            }

            return response()->json(['success' => false, 'reply' => 'Maaf, proses terlalu panjang.'], 500);

        } catch (\Exception $e) {
            Log::error('Chat Error', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': return Task::all()->toArray();
                case 'get_schedules': return Schedule::all()->toArray();
                case 'add_task': return Task::create($args)->toArray();
                default: return ['error' => 'Fungsi tidak ditemukan'];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
