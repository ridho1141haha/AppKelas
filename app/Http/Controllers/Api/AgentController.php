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
        // Handle input dari Android (Form atau JSON)
        $message = $request->input('message') ?? $request->post('message') ?? 'Halo';
        
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false, 
                'reply' => '❌ API Key Gemini belum diset.',
            ], 500);
        }

        try {
            // Definisi Tools
            $tools = [
                'function_declarations' => [
                    [
                        'name' => 'get_tasks', 
                        'description' => 'Ambil daftar tugas atau PR siswa.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'get_schedules', 
                        'description' => 'Ambil jadwal pelajaran sekolah.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'add_task',
                        'description' => 'Tambah tugas baru ke database.',
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
                    'parts' => [['text' => (string)$message]]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas'. Hari ini $today. Gunakan tools untuk melihat tugas/jadwal. Jawablah dengan singkat dan ramah.";

            $maxIterations = 3; // Batasi iterasi biar gak loop kelamaan
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]]
                    ],
                    'tools' => [$tools]
                ];

                // Pake v1 yang lebih stabil
                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    $error = $response->json();
                    Log::error('Gemini API Error', $error);
                    return response()->json([
                        'success' => false, 
                        'reply' => '❌ Google AI Error (' . $response->status() . '): ' . ($error['error']['message'] ?? 'Service Busy'),
                        'debug' => $error
                    ], 500);
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                
                if (!$content) {
                    return response()->json(['success' => false, 'reply' => '❌ AI tidak merespon.']);
                }

                $history[] = $content;
                $part = $content['parts'][0] ?? null;

                if (isset($part['functionCall'])) {
                    $name = $part['functionCall']['name'];
                    $args = $part['functionCall']['args'] ?? [];
                    
                    $result = $this->executeLocalFunction($name, $args);
                    
                    // Format response fungsi yang benar untuk Gemini 1.5
                    $history[] = [
                        'role' => 'function', 
                        'parts' => [
                            [
                                'functionResponse' => [
                                    'name' => $name,
                                    'response' => ['result' => $result] // Wrap dalam object
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

            return response()->json(['success' => false, 'reply' => '❌ Proses terlalu lama.']);

        } catch (\Exception $e) {
            Log::error('Chat Exception', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => '❌ Error: ' . $e->getMessage()], 500);
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
                    Task::create($args);
                    return "Tugas berhasil ditambahkan.";
                default: 
                    return "Fungsi tidak ditemukan.";
            }
        } catch (\Exception $e) {
            return "Error database: " . $e->getMessage();
        }
    }
}
