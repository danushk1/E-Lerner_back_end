<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class FloorPlanController extends Controller
{
 public function generateFloorPlan(Request $request)
{
    try {
        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Generate a house plan with width {$request->width}, length {$request->length}, and {$request->rooms} rooms. Return it as structured JSON.",
                    ],
                ],
            ]);

        $data = $response->json();

        if (!isset($data['choices'])) {
            return response()->json([
                'error' => 'Invalid response from OpenAI.',
                'details' => $data,
            ], 500);
        }

        return response()->json([
            'plan' => $data['choices'][0]['message']['content'],
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Request failed.',
            'message' => $e->getMessage(),
        ], 500);
    }
}
}
