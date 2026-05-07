<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $message = $request->input('message', 'Halo');
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            return response()->json(['success' => false, 'reply' => 'API Key Missing'], 500);
        }

        try {
            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $message]
                        ]
                    ]
                ]
            ];

            Log::info('Gemini Simple Payload', $payload);

            $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", $payload);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'reply' => 'Gemini Error: ' . $response->status(),
                    'error_detail' => $response->json(),
                    'sent_payload' => $payload
                ], 500);
            }

            $resData = $response->json();
            $reply = $resData['candidates'][0]['content']['parts'][0]['text'] ?? 'No response from AI';

            return response()->json([
                'success' => true,
                'reply' => $reply
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'reply' => 'System Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
