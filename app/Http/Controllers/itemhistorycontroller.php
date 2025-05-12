<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;

class ItemHistoryController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:1000',
        ]);

        $userQuery = $request->input('query');

        // STEP 1: Prepare system prompt
         $systemMessage = <<<EOT
You are an assistant that converts user queries into structured JSON for chart/report generation.
Only use these MySQL tables: item_historys, items, branches.
Ensure all column names are qualified (e.g., item_historys.item_id) to avoid ambiguity.
Supported outputs: chart, pdf, excel.
Supported chart types: pie, bar, line.

{
  "output": "pdf | excel | chart | table",
  "title": "string or null",
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

Use only these columns from `item_historys`:
- external_number, branch_id, location_id, document_number, transaction_date, description,
  item_id, quantity, free_quantity, batch_number, whole_sale_price, retial_price,
  expire_date, cost_price

You can join:
- `items.item_id` to get `item_code`, `item_Name`
- `branches.branch_id` to get `branch_name`, `address`

Only valid columns from:
- item_historys: external_number, branch_id, location_id, document_number, transaction_date, description, item_id, quantity, free_quantity, batch_number, whole_sale_price, retial_price, expire_date, cost_price
- items: item_code, item_name
- branches: branch_name, address
Do not include explanation. Return only a single valid JSON object.
EOT;

        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $userQuery],
            ],

        ]);

       $responseData = $openAiResponse->json();
        if (empty($responseData['choices'][0]['message']['content'])) {
            return response()->json(['error' => 'Invalid response from OpenAI'], 422);
        }

        $json = json_decode($responseData['choices'][0]['message']['content'], true);
        if (!$json || !isset($json['columns']) || !isset($json['aggregation'])) {
            return response()->json(['error' => 'Invalid JSON structure from OpenAI'], 422);
        }
           // Build SELECT clause
        $selectColumns = [];
        foreach ($json['columns'] as $col) {
            if (str_contains($col, '.')) {
                $selectColumns[] = $col;
            } else {
                // infer table
                if (in_array($col, ['item_code', 'item_name'])) {
                    $selectColumns[] = "items.$col";
                } elseif (in_array($col, ['branch_name', 'address'])) {
                    $selectColumns[] = "branches.$col";
                } else {
                    $selectColumns[] = "item_historys.$col";
                }
            }
        }

        // Add aggregation
        $agg = strtoupper($json['aggregation']['action']) . "(item_historys." . $json['aggregation']['field'] . ") AS value";
        $select[] = $agg;

        $sql = "SELECT " . implode(', ', $select) . " FROM item_historys
                LEFT JOIN items ON item_historys.item_id = items.item_id
                LEFT JOIN branches ON item_historys.branch_id = branches.branch_id";


        // Apply filters
           $filters = $json['filters'] ?? [];
        if (!empty($filters)) {
            $sql .= " WHERE ";
            $where = [];
            foreach ($filters as $filter) {
                $column = match (true) {
                    in_array($filter['column'], ['item_code', 'item_name']) => "items." . $filter['column'],
                    $filter['column'] === 'branch_name' => "branches." . $filter['column'],
                    default => "item_historys." . $filter['column']
                };

                if ($filter['operator'] === 'between') {
                    $where[] = "$column BETWEEN {$filter['value'][0]} AND {$filter['value'][1]}";
                } else {
                    $val = is_numeric($filter['value']) ? $filter['value'] : "'" . addslashes($filter['value']) . "'";
                    $where[] = "$column {$filter['operator']} $val";
                }
            }
            $sql .= implode(' AND ', $where);
        }

        // GROUP BY
        if (!empty($columns)) {
            $groupBy = [];
            foreach ($columns as $col) {
                if (in_array($col, ['item_code', 'item_name'])) {
                    $groupBy[] = "items.$col";
                } elseif (in_array($col, ['branch_name'])) {
                    $groupBy[] = "branches.$col";
                } else {
                    $groupBy[] = "item_historys.$col";
                }
            }
            $sql .= "GROUP BY " . implode(", ", $groupBy);
        }



       dd($sql);
        // Execute the raw SQL query
        try {
         
            $results = DB::select($sql);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process request',
                'details' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'title' => $json['title'] ?? 'Report',
            'chart_type' => $json['chart_type'] ?? 'pie', // Default to pie chart
            'data' => $results,
            'colors' => ['#3B82F6', '#EF4444', '#FACC15', '#8B5CF6'] // Blue, red, yellow, purple
        ]);
    }
}