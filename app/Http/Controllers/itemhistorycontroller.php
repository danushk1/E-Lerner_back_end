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

        // 1. System message includes exact table schema to prevent column name mistakes
        $systemMessage = <<<EOT
You are a chart assistant. Convert the user's request into a JSON object with this format:
{
  "chart_type": "bar | line | pie | scatter | table",
  "action": "sum | count | avg | max | min | none",
  "field": "column_to_aggregate",
  "group_by": "column_name_or_null",
  "filters": [
    {"column": "field_name", "operator": "= | > | < | >= | <= | between", "value": "value or [start, end]"}
  ]
}

The database table is `item_historys`.
Available columns are:
- id (int)
- external_number (string)
- branch_id (int)
- location_id (int)
- document_number (int)
- transaction_date (date)
- description (string)
- item_id (int)
- quantity (decimal)
- free_quantity (decimal)
- batch_number (string)
- whole_sale_price (decimal)
- retial_price (decimal)
- expire_date (date)
- cost_price (decimal)
- embedding (json) // We can retrieve and use this for similarity searches or recommendations
- created_at (timestamp)
- updated_at (timestamp)

Use only these columns. Do not invent any column names.
EOT;

        // 2. Ask OpenAI to convert user query to structured chart instructions
        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $query],
            ]
        ]);

        $openAiData = $openAiResponse->json();
dd($openAiData);
        $message = $openAiData['choices'][0]['message']['content'] ?? null;

        if (!$message) {
            return response()->json(['error' => 'Invalid OpenAI response'], 500);
        }

        // 3. Parse OpenAI responsep
        try {
            $instructions = json_decode($message, true);

            $chartType = $instructions['chart_type'] ?? 'table';
            $action = $instructions['action'] ?? 'none';
            $field = $instructions['field'] ?? null;
            $groupBy = $instructions['group_by'] ?? null;
            $filters = $instructions['filters'] ?? [];

            // 4. Initialize query builder
            $queryBuilder = DB::table('item_historys');

            // 5. Apply filters (including embedding if necessary)
            foreach ($filters as $filter) {
                $column = $filter['column'];
                $operator = $filter['operator'];
                $value = $filter['value'];

                if ($operator === 'between' && is_array($value)) {
                    $queryBuilder->whereBetween($column, $value);
                } else {
                    $queryBuilder->where($column, $operator, $value);
                }
            }

            // 6. Select and group (with embedding processing if necessary)
            if ($action !== 'none' && $field) {
                $select = ($groupBy ? "$groupBy, " : "") . "$action($field) as value";
                $queryBuilder->selectRaw($select);
            } elseif ($field) {
                $queryBuilder->select("$field as value");
            }

            if ($groupBy) {
                $queryBuilder->groupBy($groupBy);
            }

            // Retrieve results
            $results = $queryBuilder->get();

            // Optionally, retrieve and process embeddings if needed (e.g., for similarity searches)
            $embeddings = DB::table('item_historys')
                ->select('item_history_id', 'embedding')
                ->get();

            // Return data (including embeddings if needed)
            $formattedData = $results->map(function ($row) use ($groupBy) {
                return [
                    'name' => $groupBy ? $row->$groupBy : '',
                    'value' => $row->value,
                ];
            });

            // Optionally, process embeddings for recommendations or similarity analysis
            $embeddingData = $embeddings->map(function ($item) {
                return [
                    'id' => $item->id,
                    'embedding' => json_decode($item->embedding) // Decode the JSON embedding data
                ];
            });

            return response()->json([
                'charts' => [
                    [
                        'type' => $chartType,
                        'data' => $formattedData,
                    ]
                ],
                'embeddings' => $embeddingData, // Send embeddings if necessary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process chart query',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}