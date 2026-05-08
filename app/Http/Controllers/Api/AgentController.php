<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Task;
use App\Models\Schedule;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $message = $request->input('message') ?? $request->post('message') ?? 'Halo';
        $user = $request->user();
        $cacheKey = 'chat_history_' . ($user ? $user->id : 'guest');

        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return response()->json(['success' => false, 'reply' => '❌ API Key Missing.']);
        }

        try {
            // 1. Ambil History & Validasi Struktur (Wajib selang-seling User-Model)
            $history = Cache::get($cacheKey, []);
            
            // Tambahkan pesan user baru
            $history[] = [
                'role' => 'user',
                'parts' => [['text' => $message]]
            ];

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

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu adalah 'Asisten Kelas'. Hari ini $today. Kamu punya memori untuk mengingat obrolan sebelumnya.";

            $maxIterations = 5;
            while ($maxIterations > 0) {
                $maxIterations--;
                
                $payload = [
                    'contents' => $history,
                    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                    'tools' => [['function_declarations' => $tools['function_declarations']]]
                ];

                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}", $payload);

                if (!$response->successful()) {
                    $err = $response->json();
                    Log::error('Gemini History Error', $err);
                    // Jika error karena skema history, reset cache
                    if ($response->status() === 400) { Cache::forget($cacheKey); }
                    return response()->json(['success' => false, 'reply' => '⚠️ Gagal memproses pesan lanjutan. Coba lagi ya!', 'debug' => $err], 500);
                }

                $resData = $response->json();
                $content = $resData['candidates'][0]['content'] ?? null;
                
                if (!$content) break;

                // TAMBAHKAN RESPON AI KE HISTORY (Penting biar gak error di chat selanjutnya)
                $history[] = $content;
                $part = $content['parts'][0] ?? null;

                if (isset($part['functionCall'])) {
                    $name = $part['functionCall']['name'];
                    $args = $part['functionCall']['args'] ?? [];
                    $result = $this->executeLocalFunction($name, $args);
                    
                    $history[] = [
                        'role' => 'function', 
                        'parts' => [['functionResponse' => ['name' => $name, 'response' => ['result' => $result]]]]
                    ];
                    continue;
                } 
                
                if (isset($part['text'])) {
                    // Simpan history lengkap (User + Model) ke Cache
                    // Kita simpan 10 exchange terakhir (User & AI)
                    $finalHistory = array_slice($history, -10);
                    Cache::put($cacheKey, $finalHistory, now()->addMinutes(30));

                    return response()->json([
                        'success' => true,
                        'reply' => $part['text']
                    ]);
                }
                break;
            }

            return response()->json(['success' => false, 'reply' => '❌ Gagal merespon.']);

        } catch (\Exception $e) {
            Log::error('Memory Fix Error', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => '❌ Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': return Task::where('status', '!=', 'completed')->get()->take(5)->toArray();
                case 'get_schedules': return Schedule::all()->take(10)->toArray();
                case 'add_task': 
                    $task = Task::create($args);
                    return "SUKSES: Tugas '" . $task->title . "' sudah dicatat.";
                default: return "Fungsi tidak ditemukan.";
            }
        } catch (\Exception $e) {
            return "Error database: " . $e->getMessage();
        }
    }
}
