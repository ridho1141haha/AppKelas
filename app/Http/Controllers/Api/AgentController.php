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
        $message = trim((string)$request->input('message', 'Halo'));
        $user = $request->user();
        $cacheKey = 'chat_mem_' . ($user ? $user->id : 'guest');

        $apiKeys = array_filter([
            env('GEMINI_API_KEY'),
            env('GEMINI_API_KEY_2'),
            env('GEMINI_API_KEY_3'),
        ]);

        if (empty($apiKeys)) return response()->json(['success' => false, 'reply' => '❌ No API Key.']);

        try {
            // 1. Load History & Tambahkan Pesan User
            $history = Cache::get($cacheKey, []);
            $history[] = [
                'role' => 'user',
                'parts' => [['text' => $message]]
            ];

            $tools = [
                'function_declarations' => [
                    ['name' => 'get_tasks', 'description' => 'Melihat daftar tugas sekolah.', 'parameters' => ['type' => 'object', 'properties' => (object)[]]],
                    ['name' => 'get_schedules', 'description' => 'Melihat jadwal pelajaran sekolah.', 'parameters' => ['type' => 'object', 'properties' => (object)[]]],
                    [
                        'name' => 'add_task',
                        'description' => 'Menambah tugas baru ke database.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'subject' => ['type' => 'string'],
                                'deadline' => ['type' => 'string'],
                            ],
                            'required' => ['title', 'subject', 'deadline']
                        ]
                    ]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $sysInst = "Kamu 'Asisten Kelas'. Hari ini $today. Jawab santai. Jika user tanya jadwal/tugas, panggil fungsi.";

            $modelList = ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-flash-latest'];
            $finalReply = null;

            foreach ($apiKeys as $currentKey) {
                foreach ($modelList as $modelName) {
                    $iter = 2;
                    while ($iter > 0) {
                        $iter--;
                        
                        $payload = [
                            'contents' => $history,
                            'system_instruction' => ['parts' => [['text' => $sysInst]]],
                            'tools' => [['function_declarations' => $tools['function_declarations']]]
                        ];

                        $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$currentKey}", $payload);

                        if (!$response->successful()) {
                            $status = $response->status();
                            if (in_array($status, [429, 503, 404, 401])) break;
                            
                            $errBody = $response->json();
                            return response()->json([
                                'success' => false, 
                                'reply' => "⚠️ API Error ({$status}): " . ($errBody['error']['message'] ?? 'Check JSON'),
                                'debug' => $errBody
                            ]);
                        }

                        $resData = $response->json();
                        $candidate = $resData['candidates'][0] ?? null;
                        $content = $candidate['content'] ?? null;
                        if (!$content) break;

                        $part = $content['parts'][0] ?? null;

                        // --- ⚡ HANDLING FUNCTION CALL ---
                        if (isset($part['functionCall'])) {
                            $name = $part['functionCall']['name'];
                            $args = $part['functionCall']['args'] ?? (object)[];

                            // Simpan ke history dengan format SNAKE_CASE (Penting buat request selanjutnya)
                            $history[] = [
                                'role' => 'model',
                                'parts' => [[
                                    'function_call' => [
                                        'name' => $name,
                                        'args' => (object)$args
                                    ]
                                ]]
                            ];

                            // Eksekusi fungsi di Laravel
                            $result = $this->executeLocalFunction($name, (array)$args);

                            // Tambahkan response fungsi ke history
                            $history[] = [
                                'role' => 'function',
                                'parts' => [[
                                    'function_response' => [
                                        'name' => $name,
                                        'response' => (object)['content' => $result]
                                    ]
                                ]]
                            ];
                            continue;
                        } 
                        
                        // --- ⚡ HANDLING TEXT RESPONSE ---
                        if (isset($part['text'])) {
                            $finalReply = $part['text'];
                            $history[] = $content;
                            break 3;
                        }
                    }
                }
            }

            if ($finalReply) {
                // Simpan history seimbang (User-AI-User...) ke Cache
                Cache::put($cacheKey, array_slice($history, -6), now()->addMinutes(20));
                return response()->json(['success' => true, 'reply' => $finalReply]);
            }

            return response()->json(['success' => false, 'reply' => '⏳ Semua model lagi limit.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'reply' => '❌ Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': 
                    return Task::select('title', 'subject', 'deadline')->where('status', '!=', 'completed')->get()->toArray();
                case 'get_schedules': 
                    return Schedule::select('day', 'subjects', 'dismissal_time')->get()->toArray();
                case 'add_task': 
                    $task = Task::create($args);
                    return "Tugas '{$task->title}' berhasil dicatat di database.";
                default: return "Gak ada fungsi itu.";
            }
        } catch (\Exception $e) {
            return "Error DB: " . $e->getMessage();
        }
    }
}
