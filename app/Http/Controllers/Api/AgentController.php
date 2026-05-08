<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        return response()->json([
            'success' => true,
            'reply' => 'SERVER UPDATED! Halo Ridho, ini respon dari server yang udah diupdate. Kalau lu liat ini, berarti deploy sukses.'
        ]);
    }
}
