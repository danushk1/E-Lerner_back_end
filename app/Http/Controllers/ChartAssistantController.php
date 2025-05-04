<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\subject;
use Illuminate\Support\Facades\Http;

class ChartAssistantController extends Controller
{
    public function generate(Request $request)
    {
        $query = $request->input('query');

        // Step 1: Ask OpenAI what chart types and rules are requested
        $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a chart assistant. Convert natural language into chart instructions.'],
                ['role' => 'user', 'content' => $query]
            ],
            'functions' => [
                [
                    'name' => 'generate_chart',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'charts' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'type' => ['type' => 'string', 'enum' => ['bar', 'line', 'pie', 'scatter']],
                                        'title' => ['type' => 'string'],
                                        'sort' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                                        'color_rules' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'highest' => ['type' => 'string'],
                                                'lowest' => ['type' => 'string']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'function_call' => ['name' => 'generate_chart']
        ]);

        $charts = $response->json()['choices'][0]['message']['function_call']['arguments'] ?? null;

        if (!$charts) {
            return response()->json(['error' => 'Unable to parse chart instructions'], 422);
        }

        $parsed = json_decode($charts, true);
        $chartResponses = [];

        foreach ($parsed['charts'] as $chart) {
            $type = $chart['type'];
            $sort = $chart['sort'] ?? null;
            $colorRules = $chart['color_rules'] ?? [];

            if ($type === 'bar') {
                $query = subject::select('subject_title', 'rating');
                if ($sort === 'desc') $query->orderByDesc('rating');
                elseif ($sort === 'asc') $query->orderBy('rating');
                $chartResponses[] = [
                    'type' => 'bar',
                    'data' => $query->get()
                ];
            }

            if ($type === 'pie') {
                $data = subject::select('subject_title', 'rating')->get();
                $highest = $data->max('rating');
                $lowest = $data->min('rating');
                $chartResponses[] = [
                    'type' => 'pie',
                    'data' => $data->map(function ($item) use ($colorRules, $highest, $lowest) {
                        $color = '#ccc';
                        if ($item->rating == $highest && isset($colorRules['highest'])) $color = $colorRules['highest'];
                        if ($item->rating == $lowest && isset($colorRules['lowest'])) $color = $colorRules['lowest'];
                        return [
                            'name' => $item->subject_title,
                            'value' => $item->rating,
                            'fill' => $color
                        ];
                    })->values()
                ];
            }

            if ($type === 'line') {
                $chartResponses[] = [
                    'type' => 'line',
                    'data' => subject::orderBy('created_at')->get(['created_at', 'rating'])
                ];
            }

            if ($type === 'scatter') {
                $chartResponses[] = [
                    'type' => 'scatter',
                    'data' => subject::get(['rating', 'difficulty_level'])
                ];
            }
        }

        return response()->json(['charts' => $chartResponses]);
    }
}
