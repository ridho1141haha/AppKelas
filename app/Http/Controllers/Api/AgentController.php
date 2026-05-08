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

        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) return response()->json(['reply' => '❌ API Key Missing.']);

        try {
            // 1. Load History (Max 6 elemen)
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

            // 📋 Daftar model untuk rotasi otomatis (Failover)
            $modelList = [
                'gemini-2.0-flash',      // Jagoan utama
                'gemini-1.5-pro',        // Cadangan 1 (Pinter)
                'gemini-1.5-flash-8b',   // Cadangan 2 (Kenceng)
                'gemini-flash-latest'    // Cadangan terakhir
            ];

            $finalReply = null;

            foreach ($modelList as $modelName) {
                $maxCalls = 2; // Iterasi function calling per model
                
                while ($maxCalls > 0) {
                    $maxCalls--;
                    
                    $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}", [
                        'contents' => $history,
                        'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                        'tools' => [['function_declarations' => $tools['function_declarations']]]
                    ]);

                    // Jika Kena Rate Limit (429) atau Model Down (503), coba model selanjutnya di $modelList
                    if (!$response->successful()) {
                        if ($response->status() === 429 || $response->status() === 503 || $response->status() === 404) {
                            Log::warning("Model {$modelName} gagal ({$response->status()}), mencoba model selanjutnya...");
                            break; // Keluar dari loop while, lanjut ke model berikutnya di foreach
                        }
                        // Jika error lain (400 dll), langsung stop
                        return response()->json(['success' => false, 'reply' => '⚠️ Google AI Error (' . $response->status() . ')']);
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
                        break 2; // Berhasil! Keluar dari while dan foreach
                    }
                }
            }

            if ($finalReply) {
                Cache::put($cacheKey, array_slice($history, -6), now()->addMinutes(20));
                return response()->json(['success' => true, 'reply' => $finalReply]);
            }

            return response()->json(['success' => false, 'reply' => '⏳ Semua otak AI lagi penuh antrian nih. Coba lagi 1 menit ya Ridho!']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'reply' => '❌ Sistem Error: ' . $e->getMessage()], 500);
        }
    }

    private function executeLocalFunction($name, $args)
    {
        try {
            switch ($name) {
                case 'get_tasks': return Task::where('status', '!=', 'completed')->get()->take(5)->toArray();
                case 'get_schedules': return Schedule::all()->take(10)->toArray();
                case 'add_task': return Task::create($args) ? "Oke, udah kucatat!" : "Gagal catat.";
                default: return "Gak ada fungsi itu.";
            }
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
