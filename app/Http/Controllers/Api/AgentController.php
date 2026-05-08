<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\Schedule;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $rawMessage = $request->input('message') ?? $request->post('message') ?? 'Halo';
        $message = trim((string)$rawMessage);
        
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return response()->json(['success' => false, 'reply' => '❌ Konfigurasi API Error.']);
        }

        try {
            // --- STRATEGI HYBRID: Deteksi Konteks Manual ---
            $contextData = "";
            $loweredMsg = Str::lower($message);

            // 1. Cek jika tanya TUGAS
            if (Str::contains($loweredMsg, ['tugas', 'pr', 'pekerjaan rumah', 'tugas saya'])) {
                $tasks = Task::where('status', '!=', 'completed')->take(5)->get();
                if ($tasks->isNotEmpty()) {
                    $contextData .= "\nDAFTAR TUGAS AKTIF:\n";
                    foreach ($tasks as $t) {
                        $contextData .= "- {$t->title} (Mapel: {$t->subject}, Deadline: {$t->deadline})\n";
                    }
                } else {
                    $contextData .= "\nInfo: Saat ini tidak ada tugas aktif di database.\n";
                }
            }

            // 2. Cek jika tanya JADWAL
            if (Str::contains($loweredMsg, ['jadwal', 'pelajaran', 'kuliah', 'sekolah'])) {
                $schedules = Schedule::all()->take(10);
                if ($schedules->isNotEmpty()) {
                    $contextData .= "\nJADWAL PELAJARAN:\n";
                    foreach ($schedules as $s) {
                        $contextData .= "- {$s->day} ({$s->type}): {$s->subjects} (Pulang: {$s->dismissal_time})\n";
                    }
                }
            }

            // --- SIAPKAN PROMPT UNTUK GEMINI ---
            $today = now()->translatedFormat('l, d F Y');
            $finalPrompt = "Kamu adalah 'Asisten Kelas' yang ramah. Hari ini adalah $today.\n";
            
            if ($contextData !== "") {
                $finalPrompt .= "Berikut adalah DATA DATABASE beneran yang harus kamu sampaikan ke user:\n$contextData\n";
                $finalPrompt .= "Tugasmu: Rangkum data di atas dengan gaya bicara yang santai dan akrab. Jika user bertanya spesifik, jawab berdasarkan data tersebut.";
            } else {
                $finalPrompt .= "Jawablah pesan user berikut dengan santai: ";
            }

            $finalPrompt .= "\n\nUser: " . $message;

            // --- TEMBAK GEMINI (MODE TEXT ONLY - LEBIH STABIL) ---
            $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $finalPrompt]]
                    ]
                ]
            ]);

            if (!$response->successful()) {
                $err = $response->json();
                return response()->json([
                    'success' => false, 
                    'reply' => '⚠️ Waduh, si AI lagi capek. Coba lagi bentar lagi ya!',
                    'debug' => $err
                ], 500);
            }

            $resData = $response->json();
            $reply = $resData['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, aku bingung mau jawab apa.';

            return response()->json([
                'success' => true,
                'reply' => $reply
            ]);

        } catch (\Exception $e) {
            Log::error('Hybrid Chat Error', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'reply' => '❌ Sistem Error: ' . $e->getMessage()], 500);
        }
    }
}
