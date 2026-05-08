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

        // 1. Daftar API Keys (Ambil dari Railway Variables)
        $apiKeys = array_filter([
            env('GEMINI_API_KEY'),
            env('GEMINI_API_KEY_2'),
            env('GEMINI_API_KEY_3'),
        ]);

        if (empty($apiKeys)) {
            return response()->json(['success' => false, 'reply' => '❌ Tidak ada API Key yang terkonfigurasi.']);
        }

        try {
            // 2. Load History (Max 6 elemen)
            $history = Cache::get($cacheKey, []);
            $history[] = ['role' => 'user', 'parts' => [['text' => $message]]];

            $tools = [
                'function_declarations' => [
                    ['name' => 'get_tasks', 'description' => 'Cek daftar tugas.', 'parameters' => ['type' => 'object', 'properties' => (object)[]]],
                    ['name' => 'get_schedules', 'description' => 'Cek jadwal pelajaran.', 'parameters' => ['type' => 'object', 'properties' => (object)[]]],
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
            $systemInstruction = "Kamu 'Asisten Kelas'. Hari ini $today. Jawab santai & singkat. Gunakan tools jika perlu.";

            // 📋 Daftar model untuk rotasi
            $modelList = [
                'gemini-2.0-flash',
                'gemini-1.5-pro',
                'gemini-1.5-flash-8b',
                'gemini-flash-latest'
            ];

            $finalReply = null;

            // --- 🔄 DOUBLE ROTATION: Putar API Key, lalu Putar Model ---
            foreach ($apiKeys as $keyIndex => $currentKey) {
                foreach ($modelList as $modelName) {
                    $maxCalls = 2; // Iterasi internal function calling
                    
                    while ($maxCalls > 0) {
                        $maxCalls--;
                        
                        $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$currentKey}", [
                            'contents' => $history,
                            'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                            'tools' => [['function_declarations' => $tools['function_declarations']]]
                        ]);

                        if (!$response->successful()) {
                            $status = $response->status();
                            // Jika Limit atau Error Server, coba model/key lain
                            if ($status === 429 || $status === 503 || $status === 404 || $status === 401) {
                                Log::warning("Key #".($keyIndex+1)." Model {$modelName} gagal ({$status}), mencoba rotasi...");
                                break; // Pindah ke model selanjutnya (atau key selanjutnya jika model abis)
                            }
                            return response()->json(['success' => false, 'reply' => "⚠️ API Error ({$status})"]);
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
                                'parts' => [['functionResponse' => ['name' => $name, 'response' => ['result' => $result]]]]
                            ];
                            continue;
                        } 
                        
                        if (isset($part['text'])) {
                            $finalReply = $part['text'];
                            break 3; // SUKSES! Keluar dari semua loop
                        }
                    }
                }
            }

            if ($finalReply) {
                Cache::put($cacheKey, array_slice($history, -6), now()->addMinutes(20));
                return response()->json(['success' => true, 'reply' => $finalReply]);
            }

            return response()->json(['success' => false, 'reply' => '⏳ Semua akun & model AI lagi limit nih. Coba lagi 1 menit ya Ridho!']);

        } catch (\Exception $e) {
            Log::error('Super Rotation Error', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => '❌ Sistem Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': return Task::where('status', '!=', 'completed')->get()->take(5)->toArray();
                case 'get_schedules': return Schedule::all()->take(10)->toArray();
                case 'add_task': return Task::create($args) ? "Udah kucatat ya!" : "Gagal catat.";
                default: return "Fungsi gak ada.";
            }
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
