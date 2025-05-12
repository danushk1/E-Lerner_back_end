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

Do not include explanation. Return only a single valid JSON object.
EOT;

        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $userQuery],
            ],

        ]);

       $openAiResponseData = $openAiResponse->json();
        
        // Check if there's a valid response
        if (empty($openAiResponseData['choices'])) {
            return response()->json(['error' => 'Invalid response from OpenAI', 'details' => $openAiResponseData], 422);
        }

        $structured = $openAiResponseData['choices'][0]['message']['content'] ?? null;
        
        if (!$structured) {
            return response()->json(['error' => 'Invalid response from OpenAI. No content found.'], 422);
        }

        // Decode the structured JSON from OpenAI
          $json = json_decode($structured, true);

        if (!isset($json['action']) || !isset($json['field'])) {
            return response()->json(['error' => 'Missing required fields in response.'], 422);
        }

        // Prepare the query parts based on OpenAI response
        $field = "item_historys." . $json['field'];
        $aggregation = $json['aggregation']['action'] ?? 'sum';
        $aggAlias = 'value';
        $groupBy = $json['group_by'] ? "item_historys." . $json['group_by'] : null;
        $filters = $json['filters'] ?? [];

        // Start building the base query
        $sql = "SELECT item_historys.item_id, items.item_code, items.item_name, SUM(item_historys.quantity) AS $aggAlias
                FROM item_historys
                LEFT JOIN items ON item_historys.item_id = items.item_id
                LEFT JOIN branches ON item_historys.branch_id = branches.branch_id";

        // Initialize filter condition (to handle dynamic WHERE)
       $whereClauses = [];

foreach ($filters as $filter) {
    $column = $filter['column'];
    $operator = strtolower($filter['operator']);
    $value = $filter['value'];
//dump($filter);
    // Determine correct table prefix
    if (in_array($column, ['item_code', 'item_name'])) {
        $qualifiedColumn = "items.$column";
    } elseif (in_array($column, ['branch_name', 'address'])) {
        $qualifiedColumn = "branches.$column";
    } else {
        $qualifiedColumn = "item_historys.$column";
    }

    // Apply filter logic
    if ($operator === 'between' && is_array($value) && count($value) === 2) {
        $whereClauses[] = "$qualifiedColumn BETWEEN '{$value[0]}' AND '{$value[1]}'";
    } else {
        $escapedValue = is_string($value) ? addslashes($value) : $value;
        $whereClauses[] = "$qualifiedColumn $operator '$escapedValue'";
    }
}


        // If there are filters, append them to the query
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        // Apply grouping if necessary
        if ($groupBy) {
            $sql .= " GROUP BY item_historys.item_id, items.item_code, items.item_name";
        }
        // Execute the raw SQL query
        try {
           dd($sql);
            $results = DB::select(DB::raw($sql));

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