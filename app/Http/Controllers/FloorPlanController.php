<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class FloorPlanController extends Controller
{
  public function generate(Request $request)
    {
        $description = "Generate a house floor plan for land of width {$request->width} ft and length {$request->length} ft with {$request->rooms} rooms.";

        $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a house floor plan assistant.'],
                ['role' => 'user', 'content' => $description],
            ],
        ]);

        return response()->json([
            'plan' => $response->json()['choices'][0]['message']['content']
        ]);
    }
}
