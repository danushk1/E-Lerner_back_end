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

    // 1. Ask OpenAI to interpret the query
    $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-4',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a chart assistant. Convert the user query into structured JSON with the format:
{
  "chart_type": "bar | line | pie | scatter | table",
  "action": "sum | count | avg | max | min | none",
  "field": "column_to_aggregate",
  "group_by": "column_name_or_null",
  "filters": [
    {"column": "field_name", "operator": "= | > | < | >= | <= | between", "value": "value or [start, end]"}
  ]
}'
            ],
            ['role' => 'user', 'content' => $query]
        ]
    ]);

    $responseContent = $openAiResponse->json();

    if (!isset($responseContent['choices'][0]['message']['content'])) {
        return response()->json(['error' => 'OpenAI response missing'], 500);
    }

    try {
        $instructions = json_decode($responseContent['choices'][0]['message']['content'], true);

        $chartType = $instructions['chart_type'] ?? 'table';
        $action = $instructions['action'] ?? 'none';
        $field = $instructions['field'] ?? null;
        $groupBy = $instructions['group_by'] ?? null;
        $filters = $instructions['filters'] ?? [];

        $queryBuilder = DB::table('item_historys');

        // 2. Apply filters
        foreach ($filters as $filter) {
            $column = $filter['column'];
            $operator = $filter['operator'];
            $value = $filter['value'];

            if ($operator === 'between' && is_array($value) && count($value) === 2) {
                $queryBuilder->whereBetween($column, [$value[0], $value[1]]);
            } else {
                $queryBuilder->where($column, $operator, $value);
            }
        }

        // 3. Select columns
        if ($action !== 'none' && $field) {
            $queryBuilder->selectRaw(($groupBy ? "$groupBy, " : "") . "$action($field) as value");
        } elseif ($field) {
            $queryBuilder->select($field . ' as value');
        }

        if ($groupBy) {
            $queryBuilder->groupBy($groupBy);
        }

        $data = $queryBuilder->get();

        // 4. Format for frontend
        $formattedData = $data->map(function ($row) use ($groupBy) {
            return [
                'name' => $groupBy ? $row->$groupBy : '',
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
        return response()->json([
            'error' => 'Processing error',
            'details' => $e->getMessage()
        ], 500);
    }
}

}