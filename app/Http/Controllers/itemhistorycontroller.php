<?php

namespace App\Http\Controllers;

use App\Models\item_history;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


class itemhistorycontroller extends Controller
{
    public function generate(Request $request)
    {
        $query = $request->input('query');

        // Step 1: Ask OpenAI to convert to chart instructions
        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a chart assistant. Given a natural language query, respond ONLY with a JSON object using the following structure:
{
  "chart_type": "bar | line | pie | scatter",
  "action": "sum | count | avg | max | min",
  "field": "field_name_in_table",
  "group_by": "field_name_in_table"
}'
                ],
                ['role' => 'user', 'content' => $query]
            ]
        ]);

        $responseContent = $openAiResponse->json();

        if (!isset($responseContent['choices'][0]['message']['content'])) {
            return response()->json(['error' => 'Invalid OpenAI response'], 500);
        }

        $instructionJson = $responseContent['choices'][0]['message']['content'];

        try {
            $instructions = json_decode($instructionJson, true);

            $chartType = $instructions['chart_type'];
            $action = $instructions['action'];
            $field = $instructions['field'];
            $groupBy = $instructions['group_by'];

            $allowedActions = ['sum', 'count', 'avg', 'max', 'min'];
            $allowedFields = [
                'quantity', 'free_quantity', 'whole_sale_price',
                'retial_price', 'cost_price', 'item_id',
                'branch_id', 'location_id'
            ];

            if (!in_array($action, $allowedActions) || !in_array($field, $allowedFields) || !in_array($groupBy, $allowedFields)) {
                return response()->json(['error' => 'Invalid field or action'], 400);
            }

            $rawData = DB::table('item_histories')
                ->select($groupBy, DB::raw("{$action}({$field}) as value"))
                ->groupBy($groupBy)
                ->get();

            // Optional: format keys to frontend-friendly names
            $formattedData = $rawData->map(function ($row) use ($groupBy) {
                return [
                    'name' => $row->$groupBy,
                    'value' => $row->value
                ];
            });

            return response()->json([
                'charts' => [
                    [
                        'type' => $chartType,
                        'data' => $formattedData
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to parse or process instructions', 'details' => $e->getMessage()], 500);
        }
    }
}