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
            return response()->json(['success' => false, 'reply' => '❌ API Key Missing.']);
        }

        try {
            // 🛠️ Definisi Tools (Agar AI bisa Baca & Tulis)
            $tools = [
                'function_declarations' => [
                    [
                        'name' => 'get_tasks', 
                        'description' => 'Melihat daftar tugas sekolah yang ada.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'get_schedules', 
                        'description' => 'Melihat jadwal pelajaran sekolah.', 
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ],
                    [
                        'name' => 'add_task',
                        'description' => 'Menambahkan tugas baru ke database.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string', 'description' => 'Judul tugas'],
                                'subject' => ['type' => 'string', 'description' => 'Mata pelajaran'],
                                'deadline' => ['type' => 'string', 'description' => 'Format YYYY-MM-DD'],
                                'description' => ['type' => 'string', 'description' => 'Detail tugas']
                            ],
                            'required' => ['title', 'subject', 'deadline']
                        ]
                    ]
                ]
            ];

            // 📜 Setup History & System Instruction
            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas' yang ramah. Hari ini adalah $today. Kamu bisa MEMBACA dan MENULIS data tugas/jadwal menggunakan tools. Jika user minta tambah tugas, kamu WAJIB panggil fungsi add_task.";

            $history = [
                [
                    'role' => 'user',
                    'parts' => [['text' => $message]]
                ]
            ];

            $maxIterations = 5;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]]
                    ],
                    'tools' => [['function_declarations' => $tools['function_declarations']]]
                ];

                // Pake model terbaru yang paling stabil
                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    $err = $response->json();
                    Log::error('Gemini Write Error', $err);
                    return response()->json([
                        'success' => false, 
                        'reply' => '⚠️ Gagal akses AI (Error ' . $response->status() . '). Coba lagi ya!',
                        'debug' => $err
                    ], 500);
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                
                if (!$content) break;

                $history[] = $content;
                $part = $content['parts'][0] ?? null;

                // ⚡ DETEKSI CALL FUNCTION (PENTING BUAT NYIMPEN DATA)
                if (isset($part['functionCall'])) {
                    $name = $part['functionCall']['name'];
                    $args = $part['functionCall']['args'] ?? [];
                    
                    // Eksekusi fungsi di Laravel
                    $result = $this->executeLocalFunction($name, $args);
                    
                    // Kirim balik hasil eksekusi ke AI
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
                    continue; // Lanjut iterasi biar AI bisa konfirmasi "Sudah saya simpan"
                } 
                
                if (isset($part['text'])) {
                    return response()->json(['success' => true, 'reply' => $part['text']]);
                }

                break;
            }

            return response()->json(['success' => false, 'reply' => '❌ Gagal memproses permintaan.']);

        } catch (\Exception $e) {
            Log::error('Write Chat Error', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => '❌ Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': 
                    return Task::where('status', '!=', 'completed')->get()->take(5)->toArray();
                case 'get_schedules': 
                    return Schedule::all()->take(10)->toArray();
                case 'add_task': 
                    $task = Task::create([
                        'title' => $args['title'] ?? 'Tugas Baru',
                        'subject' => $args['subject'] ?? 'Umum',
                        'deadline' => $args['deadline'] ?? now()->format('Y-m-d'),
                        'description' => $args['description'] ?? '',
                        'status' => 'pending'
                    ]);
                    return "SUKSES: Tugas '" . $task->title . "' beneran udah masuk database.";
                default: 
                    return "Fungsi tidak ditemukan.";
            }
        } catch (\Exception $e) {
            return "Gagal akses database: " . $e->getMessage();
        }
    }
}
