<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Schedule;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Cache;

class AgentController extends Controller
{
    /**
     * chat(): Mengirim pesan ke Gemini AI dengan dukungan Function Calling dan Memory
     */
    public function chat(Request $request)
    {
        Log::info('Chat endpoint hit', ['request' => $request->all()]);
        try {
            $request->validate([
                'message' => 'required|string',
            ]);

            $userMessage = $request->input('message');
            $apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY'));
            $userId = auth()->id() ?? 'guest'; // Pake ID user buat kunci memori

            if (!$apiKey) {
                return response()->json(['success' => false, 'message' => 'API Key Gemini belum diset'], 500);
            }

            // 1. Ambil History dari Cache
            $cacheKey = "chat_history_{$userId}";
            $history = Cache::get($cacheKey, []);

            // Buat temp history buat request ini (biar nggak ngerusak cache kalau gagal)
            $currentRequestHistory = $history;
            $currentRequestHistory[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

            // Batasi history (maks 10 pesan terakhir)
            if (count($currentRequestHistory) > 10) {
                $currentRequestHistory = array_slice($currentRequestHistory, -10);
            }

            $tools = [
                [
                    'function_declarations' => [
                        [
                            'name' => 'add_task',
                            'description' => 'Menambahkan tugas baru.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'subject' => ['type' => 'string'],
                                    'deadline' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:MM:SS'],
                                    'description' => ['type' => 'string']
                                ],
                                'required' => ['title', 'subject', 'deadline']
                            ]
                        ],
                        [
                            'name' => 'update_task',
                            'description' => 'Mengedit data tugas. Gunakan get_tasks dulu untuk mencari ID jika belum tahu.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'title' => ['type' => 'string'],
                                    'subject' => ['type' => 'string'],
                                    'deadline' => ['type' => 'string'],
                                    'status' => ['type' => 'string', 'description' => 'pending atau completed']
                                ],
                                'required' => ['id']
                            ]
                        ],
                        [
                            'name' => 'delete_task',
                            'description' => 'Menghapus tugas berdasarkan ID.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer']
                                ],
                                'required' => ['id']
                            ]
                        ],
                        [
                            'name' => 'get_tasks',
                            'description' => 'Mengambil daftar tugas.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string']
                                ]
                            ]
                        ],
                        [
                            'name' => 'get_schedules',
                            'description' => 'Mengambil jadwal pelajaran.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'day' => ['type' => 'string']
                                ]
                            ]
                        ],
                        [
                            'name' => 'add_schedule',
                            'description' => 'Menambah jadwal pelajaran baru.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'day' => ['type' => 'string', 'description' => 'Hari dalam bahasa Indonesia (Senin, Selasa, Rabu, Kamis, Jumat, Sabtu)'],
                                    'subject' => ['type' => 'string', 'description' => 'Nama mata pelajaran'],
                                    'start_time' => ['type' => 'string', 'description' => 'Jam mulai format HH:MM (misal 08:00)'],
                                    'end_time' => ['type' => 'string', 'description' => 'Jam selesai format HH:MM (misal 09:30)'],
                                    'teacher' => ['type' => 'string', 'description' => 'Nama guru/dosen pengajar (opsional)']
                                ],
                                'required' => ['day', 'subject', 'start_time', 'end_time']
                            ]
                        ],
                        [
                            'name' => 'add_material',
                            'description' => 'Menambah materi pelajaran baru.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'subject' => ['type' => 'string'],
                                    'description' => ['type' => 'string']
                                ],
                                'required' => ['title', 'subject']
                            ]
                        ]
                    ]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas' yang cerdas. Hari ini $today. 
            Ingat konteks percakapan. Kamu punya akses penuh buat nambah, ngedit, atau hapus data tugas dan jadwal. 
            Kalau user mau hapus/edit tapi kamu belum tahu ID-nya, panggil fungsi 'get_tasks' atau 'get_schedules' dulu buat nyari ID-nya, baru jalankan aksi hapus/edit.";

            // 2. Kirim ke Gemini dengan History
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
            $part = $candidate['content']['parts'][0] ?? null;

            // 3. Handle Function Calling
            $maxIterations = 5;
            while (isset($part['functionCall']) && $maxIterations > 0) {
                $maxIterations--;
                $functionCall = $part['functionCall'];
                $functionName = $functionCall['name'];
                $args = $functionCall['args'];

                $functionResult = $this->executeLocalFunction($functionName, $args);

                // Update temp history
                $currentRequestHistory[] = $candidate['content'];
                $currentRequestHistory[] = [
                    'role' => 'function',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $functionName,
                                'response' => ['content' => $functionResult]
                            ]
                        ]
                    ]
                ];

                $geminiResult = $this->callGemini($apiKey, $currentRequestHistory, $tools, $systemInstruction);
                if ($geminiResult['error'] ?? false) break;
                $response = $geminiResult['data'];

                $candidate = $response['candidates'][0] ?? null;
                $part = $candidate['content']['parts'][0] ?? null;
            }

            // Kalau semuanya SUKSES, baru simpan ke Cache permanen
            if ($candidate && isset($candidate['content'])) {
                $currentRequestHistory[] = $candidate['content'];
                Cache::put($cacheKey, $currentRequestHistory, now()->addMinutes(30));
            }

            return response()->json([
                'success' => true,
                'message' => 'Balasan dari AI',
                'reply'   => $part['text'] ?? 'Maaf, aku tidak mengerti.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in chat', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function callGemini($apiKey, $contents, $tools, $systemInstruction)
    {
        $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
            'contents' => $contents,
            'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
            'tools' => $tools
        ]);

        if (!$response->successful()) {
            Log::error('Gemini API request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500)
            ]);
            return ['error' => true, 'status' => $response->status(), 'data' => null];
        }

        return ['error' => false, 'status' => 200, 'data' => $response->json()];
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            $result = match ($name) {
                'add_task' => Task::create($args)->toArray(),
                'update_task' => $this->handleUpdateTask($args),
                'delete_task' => $this->handleDeleteTask($args),
                'get_tasks' => $this->handleGetTasks($args),
                'get_schedules' => $this->handleGetSchedules($args),
                'add_schedule' => Schedule::create($args)->toArray(),
                'add_material' => Material::create($args)->toArray(),
                default => ['error' => 'Fungsi tidak ditemukan'],
            };
            return $result;
        } catch (\Exception $e) {
            Log::error("Function execution error: {$name}", ['error' => $e->getMessage()]);
            return ['error' => "Gagal menjalankan {$name}: " . $e->getMessage()];
        }
    }

    private function handleUpdateTask($args)
    {
        $task = Task::find($args['id']);
        if (!$task) return ['error' => "Tugas ID {$args['id']} tidak ditemukan."];
        $task->update($args);
        return ['success' => true, 'message' => "Tugas '{$task->title}' berhasil diperbarui.", 'data' => $task->fresh()->toArray()];
    }

    private function handleDeleteTask($args)
    {
        $task = Task::find($args['id']);
        if (!$task) return ['error' => "Tugas ID {$args['id']} tidak ditemukan."];
        $title = $task->title;
        $task->delete();
        return ['success' => true, 'message' => "Tugas '{$title}' berhasil dihapus."];
    }

    private function handleGetTasks($args)
    {
        $query = Task::query();
        if (isset($args['status'])) $query->where('status', $args['status']);
        return $query->get()->toArray();
    }

    private function handleGetSchedules($args)
    {
        $query = Schedule::query();
        if (isset($args['day'])) $query->where('day', $args['day']);
        return $query->get()->toArray();
    }
}
