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
        try {
            $response = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }
}
