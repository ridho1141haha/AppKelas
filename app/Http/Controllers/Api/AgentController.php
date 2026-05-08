<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) return response()->json(['reply' => 'API Key Missing']);

        try {
            $response = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");
            $data = $response->json();
            
            $models = [];
            if (isset($data['models'])) {
                foreach ($data['models'] as $m) {
                    $models[] = $m['name'];
                }
            }

            return response()->json([
                'success' => true,
                'reply' => 'Daftar model tersedia: ' . implode(', ', $models),
                'raw' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['reply' => 'Error: ' . $e->getMessage()]);
        }
    }
}
