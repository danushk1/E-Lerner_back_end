<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;

class OpenAIController extends Controller
{
   public function chat(Request $request)
{
    $request->validate([
        'message' => 'required|string',
    ]);

    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $request->message],
            ],
        ]);


        if ($response->failed()) {
           
            return response()->json([
                'error' => 'OpenAI API call failed',
                'details' => json_decode($response->body(), true),
            ], $response->status());
        }

        return response()->json([
            'reply' => $response['choices'][0]['message']['content']
        ]);
    } catch (\Exception $e) {
        
        return response()->json(['error' => 'Server error', 'message' => $e->getMessage()], 500);
    }
}
// system prompt add 
}
