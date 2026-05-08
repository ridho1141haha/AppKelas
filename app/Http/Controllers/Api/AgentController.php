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
            $history = Cache::get($cacheKey, []);
            $history[] = ['role' => 'user', 'parts' => [['text' => $message]]];

            $tools = [
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
                            ],
                            'required' => ['title', 'subject', 'deadline']
                        ]
                    ]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $sysInst = "Kamu 'Asisten Kelas'. Hari ini $today. Jawab santai. Jika user tanya jadwal/tugas, panggil fungsi yang tersedia.";

            $modelList = ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash-8b', 'gemini-flash-latest'];
            $finalReply = null;

            foreach ($apiKeys as $keyIndex => $currentKey) {
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
                            if (in_array($status, [429, 503, 404, 401])) {
                                break; // Pindah model/key
                            }
                            // Jika 400, tampilkan detail error biar Ridho bisa liat di HP
                            $errBody = $response->json();
                            return response()->json([
                                'success' => false, 
                                'reply' => "⚠️ API Error ({$status}): " . ($errBody['error']['message'] ?? 'Check logs'),
                                'debug' => $errBody
                            ]);
                        }

                        $data = $response->json();
                        $candidate = $data['candidates'][0] ?? null;
                        $content = $candidate['content'] ?? null;
                        if (!$content) break;

                        $history[] = $content;
                        $part = $content['parts'][0] ?? null;

                        if (isset($part['functionCall'])) {
                            $res = $this->executeLocalFunction($part['functionCall']['name'], $part['functionCall']['args'] ?? []);
                            $history[] = [
                                'role' => 'function', 
                                'parts' => [['functionResponse' => ['name' => $part['functionCall']['name'], 'response' => ['result' => $res]]]]
                            ];
                            continue;
                        } 
                        
                        if (isset($part['text'])) {
                            $finalReply = $part['text'];
                            break 3;
                        }
                    }
                }
            }

            if ($finalReply) {
                Cache::put($cacheKey, array_slice($history, -6), now()->addMinutes(20));
                return response()->json(['success' => true, 'reply' => $finalReply]);
            }

            return response()->json(['success' => false, 'reply' => '⏳ Maaf, semua otak AI lagi limit.']);

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
                    return "Tugas '{$task->title}' berhasil dicatat.";
                default: return "Fungsi tidak ditemukan.";
            }
        } catch (\Exception $e) {
            return "Error database: " . $e->getMessage();
        }
    }
}
