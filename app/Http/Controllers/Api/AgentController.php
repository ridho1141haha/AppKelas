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
    /**
     * Chat endpoint for AI Agent
     */
    public function chat(Request $request)
    {
        Log::info('Chat endpoint hit', ['request' => $request->all()]);
        
        $message = $request->input('message');
        $apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY'));

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'API Key Gemini belum diset.'], 500);
        }

        try {
            // 1. Persiapkan Tools (Function Declarations)
            $tools = [
                [
                    'function_declarations' => [
                        [
                            'name' => 'get_tasks',
                            'description' => 'Ambil daftar tugas sekolah yang ada.',
                            'parameters' => ['type' => 'object', 'properties' => (object)[]]
                        ],
                        [
                            'name' => 'add_task',
                            'description' => 'Tambah tugas sekolah baru.',
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
                        ],
                        [
                            'name' => 'update_task',
                            'description' => 'Update status atau detail tugas.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'status' => ['type' => 'string', 'enum' => ['pending', 'completed']]
                                ],
                                'required' => ['id']
                            ]
                        ],
                        [
                            'name' => 'delete_task',
                            'description' => 'Hapus tugas berdasarkan ID.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer']
                                ],
                                'required' => ['id']
                            ]
                        ],
                        [
                            'name' => 'get_schedules',
                            'description' => 'Ambil jadwal pelajaran.',
                            'parameters' => ['type' => 'object', 'properties' => (object)[]]
                        ],
                        [
                            'name' => 'add_schedule',
                            'description' => 'Tambah jadwal pelajaran baru.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'day' => ['type' => 'string'],
                                    'type' => ['type' => 'string', 'description' => 'Normatif-Adaptif / Produktif Minggu 1 / Produktif Minggu 2'],
                                    'subjects' => ['type' => 'string', 'description' => 'List mata pelajaran dipisah tanda strip'],
                                    'dismissal_time' => ['type' => 'string']
                                ],
                                'required' => ['day', 'type', 'subjects', 'dismissal_time']
                            ]
                        ]
                    ]
                ]
            ];

            // Build History
            $currentRequestHistory = [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $message]
                    ]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas' yang cerdas. Hari ini $today. 
            Ingat konteks percakapan. Kamu punya akses penuh buat nambah, ngedit, atau hapus data tugas dan jadwal. 
            Kalau user mau hapus/edit tapi kamu belum tahu ID-nya, panggil fungsi 'get_tasks' atau 'get_schedules' dulu buat nyari ID-nya, baru jalankan aksi hapus/edit.";

            // 2. Kirim ke Gemini (Loop untuk handle multiple function calls)
            $maxIterations = 5;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $geminiResult = $this->callGemini($apiKey, $currentRequestHistory, $tools, $systemInstruction);
                
                if ($geminiResult['error'] ?? false) {
                    $httpCode = $geminiResult['status'] === 429 ? 429 : 500;
                    $msg = $geminiResult['status'] === 429 
                        ? 'Kuota API Gemini habis. Coba lagi dalam beberapa menit ya!' 
                        : 'Gagal menghubungi Gemini AI (status: ' . $geminiResult['status'] . ')';
                    return response()->json(['success' => false, 'message' => $msg], $httpCode);
                }

                $response = $geminiResult['data'];
                $candidate = $response['candidates'][0] ?? null;
                $content = $candidate['content'] ?? null;
                $part = $content['parts'][0] ?? null;

                // Simpan model response ke history
                $currentRequestHistory[] = $content;

                // Jika ada function call, eksekusi
                if (isset($part['functionCall'])) {
                    $fn = $part['functionCall'];
                    $name = $fn['name'];
                    $args = $fn['args'] ?? [];

                    $result = $this->executeLocalFunction($name, $args);

                    // Tambahkan hasil fungsi ke history untuk dikirim balik (Pake role: function!)
                    $currentRequestHistory[] = [
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
                    // Lanjut loop untuk minta respon text dari model berdasarkan hasil fungsi
                } else {
                    // Jika tidak ada function call lagi, berarti ini respon final
                    $reply = $part['text'] ?? 'Maaf, aku bingung mau jawab apa.';
                    return response()->json([
                        'success' => true,
                        'reply' => $reply
                    ]);
                }
            }

            return response()->json(['success' => false, 'message' => 'Terlalu banyak langkah fungsi.'], 500);

        } catch (\Exception $e) {
            Log::error('Chat Error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }

    private function callGemini($apiKey, $contents, $tools, $systemInstruction)
    {
        // Pake model 1.5-flash biar lebih stabil kuotanya
        $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
            'contents' => $contents,
            'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
            'tools' => $tools
        ]);

        if (!$response->successful()) {
            Log::error('Gemini API request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 1000)
            ]);
            return ['error' => true, 'status' => $response->status(), 'data' => null];
        }

        return ['error' => false, 'status' => 200, 'data' => $response->json()];
    }

    private function executeLocalFunction($name, $args)
    {
        Log::info("Executing local function: $name", ['args' => $args]);
        
        try {
            switch ($name) {
                case 'get_tasks':
                    return Task::orderBy('deadline', 'asc')->get()->toArray();

                case 'add_task':
                    return Task::create($args)->toArray();

                case 'update_task':
                    $task = Task::find($args['id']);
                    if (!$task) return ['error' => 'Tugas tidak ditemukan'];
                    $task->update($args);
                    return $task->toArray();

                case 'delete_task':
                    $task = Task::find($args['id']);
                    if (!$task) return ['error' => 'Tugas tidak ditemukan'];
                    $task->delete();
                    return ['success' => true, 'message' => 'Tugas berhasil dihapus'];

                case 'get_schedules':
                    return Schedule::all()->toArray();

                case 'add_schedule':
                    return Schedule::create($args)->toArray();

                default:
                    return ['error' => "Fungsi $name tidak ditemukan"];
            }
        } catch (\Exception $e) {
            Log::error("Function Execution Error: $name", ['msg' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }
}
