<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;

class itemhistorycontroller extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:1000',
        ]);

        $query = $request->input('query');


        $systemMessage = <<<EOT
        You are a strict data assistant. Convert the user's query into this EXACT JSON format:
        
        {
          "output": "pdf | excel | chart | table",
          "title": "Report title or null",
          "chart_type": "bar | line | pie | scatter | table",
          "action": "sum | count | avg | max | min | none",
          "field": "column_to_aggregate or null",
          "group_by": "column_name or null",
          "filters": [
            {"column": "field_name", "operator": "= | > | < | >= | <= | between", "value": "value or [start, end]"}
          ],
           "columns": ["column1", "column2", "column3"],
            "aggregation": {"action": "sum | avg | count", "field": "field_name"}
        }
        
        Use only these columns from the `item_historys` table:
        - item_history_id, external_number, branch_id, location_id, document_number, transaction_date, description, item_id, quantity, free_quantity, batch_number, whole_sale_price, retial_price, expire_date, cost_price, created_at, updated_at
        
        To get `item_Name`, join `items.item_id `
        To get `branch_name`, join `branches.branch_id`
        
        DO NOT return explanation. ONLY return a valid JSON object.
        EOT;

        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemMessage],
                    ['role' => 'user', 'content' => $query],
                ],
                'temperature' => 0.3,
            ]);

        if ($openAiResponse->failed()) {

            return response()->json(['error' => 'Failed to connect to OpenAI API'], 503);
        }


        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $query],
            ],

        ]);

        $openAiData = $openAiResponse->json();
        $message = $openAiData['choices'][0]['message']['content'] ?? null;

        if (!$message) {

            return response()->json(['error' => 'Invalid OpenAI response'], 500);
        }

     try {
            // Decode the structured instruction returned by OpenAI
            $instructions = json_decode($message, true);
            if (!is_array($instructions)) {
                throw new \Exception('Invalid JSON from OpenAI');
            }

            $outputType = $instructions['output'] ?? 'table';
            $chartType = $instructions['chart_type'] ?? 'pie';
            $filters = $instructions['filters'] ?? [];
            $columns = $instructions['columns'] ?? [];
            $aggregation = $instructions['aggregation'] ?? null; // Now this will be set correctly

            // Build the query based on the OpenAI instruction
            $queryBuilder = DB::table('item_historys')
                ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
                ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id');

            // Apply the filters dynamically based on what OpenAI returns
            foreach ($filters as $filter) {
                $column = $filter['column'];
                $operator = $filter['operator'];
                $value = $filter['value'];
                $queryBuilder->where($column, $operator, $value);
            }

            // Apply aggregation if specified (e.g., sum of quantity)
            if ($aggregation && isset($aggregation['action']) && isset($aggregation['field'])) {
                $queryBuilder->selectRaw("$aggregation[action]($aggregation[field]) as value");
            }

            // Apply column selection for table output
            if (empty($columns)) {
                $columns = ['item_historys.transaction_date', 'items.item_Name', 'branches.branch_name', 'item_historys.quantity'];
            }

            $queryBuilder->select($columns);
            $results = $queryBuilder->get();

            // Format results into the desired structure for output
            $formattedData = $results->map(function ($row) use ($columns) {
                $data = [];
                foreach ($columns as $column) {
                    $data[last(explode('.', $column))] = $row->$column ?? null;
                }
                return $data;
            });

            // Return the formatted data based on the output type
            if ($outputType === 'chart') {
                return response()->json([
                    'charts' => [
                        [
                            'type' => $chartType,
                            'data' => $formattedData,
                        ]
                    ]
                ]);
            } elseif ($outputType === 'table') {
                return response()->json([
                    'table' => $formattedData,
                ]);
            }

        } catch (\JsonException $e) {
            return response()->json(['error' => 'Invalid JSON from OpenAI'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to process request', 'details' => $e->getMessage()], 500);
        }
    }
}
