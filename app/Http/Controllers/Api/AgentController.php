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
        if (!$apiKey) return response()->json(['reply' => 'API Key Missing']);

        try {
            // 1. Ambil History Terakhir (Maksimal 6 pesan biar hemat token/quota)
            $history = Cache::get($cacheKey, []);
            $history[] = ['role' => 'user', 'parts' => [['text' => $message]]];

            $tools = [
                'function_declarations' => [
                    ['name' => 'get_tasks', 'description' => 'Cek daftar tugas sekolah.', 'parameters' => ['type' => 'object', 'properties' => (object)[]]],
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
                                'description' => ['type' => 'string']
                            ],
                            'required' => ['title', 'subject', 'deadline']
                        ]
                    ]
                ]
            ];

            $today = now()->translatedFormat('l, d F Y');
            $systemInstruction = "Kamu 'Asisten Kelas'. Hari ini $today. Gunakan tools jika perlu. Jawab singkat & santai.";

            $maxCalls = 2; // Batasi cuma 2 kali panggil API per chat biar kuota awet
            while ($maxCalls > 0) {
                $maxCalls--;
                
                $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                    'contents' => $history,
                    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                    'tools' => [['function_declarations' => $tools['function_declarations']]]
                ]);

                if (!$response->successful()) {
                    $err = $response->json();
                    if ($response->status() === 429) {
                        return response()->json(['success' => false, 'reply' => '⏳ Jatah AI lagi abis (Limit 20/hari). Coba lagi besok ya Ridho!']);
                    }
                    Cache::forget($cacheKey); // Reset history kalau error skema
                    return response()->json(['success' => false, 'reply' => '⚠️ Error Google AI (' . $response->status() . ')']);
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
                    // Simpan 6 pesan terakhir ke cache (User + AI)
                    Cache::put($cacheKey, array_slice($history, -6), now()->addMinutes(20));
                    return response()->json(['success' => true, 'reply' => $part['text']]);
                }
                break;
            }

            return response()->json(['success' => false, 'reply' => '❌ Gagal merespon.']);

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
                case 'add_task': return Task::create($args) ? "Berhasil dicatat!" : "Gagal mencatat.";
                default: return "Fungsi tidak ditemukan.";
            }
        } catch (\Exception $e) {
            return "Error DB: " . $e->getMessage();
        }
    }
}
