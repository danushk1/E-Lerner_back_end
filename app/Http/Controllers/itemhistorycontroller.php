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

        if (empty($openAiResponseData['choices'])) {
            return response()->json(['error' => 'Invalid response from OpenAI', 'details' => $openAiResponseData], 422);
        }

        $structured = $openAiResponseData['choices'][0]['message']['content'] ?? null;

        if (!$structured) {
            return response()->json(['error' => 'Invalid response from OpenAI. No content found.'], 422);
        }

        $json = json_decode($structured, true);

        if (!isset($json['action']) || !isset($json['field'])) {
            return response()->json(['error' => 'Missing required fields in response.'], 422);
        }

        $select = [];
        $userColumns = $json['columns'] ?? [];

        $select = [];
foreach ($userColumns as $col) {
    // Skip raw quantity unless explicitly grouped
    if ($col === 'quantity') {
        continue; // Don't add quantity to select if we're aggregating it
    }

    // Add fields from items, branches, or item_historys to select
    $select[] = match (true) {
        in_array($col, ['item_code', 'item_name']) => "items.$col",
        $col === 'branch_name' => "branches.$col",
        str_starts_with($col, 'items.') => $col,
        str_starts_with($col, 'branches.') => $col,
        default => "$col"
    };
}



        if (isset($json['aggregation']['action'], $json['aggregation']['field'])) {
             $field = str_replace(['item_historys.', 'items.', 'branches.'], '', $json['aggregation']['field']);
            $agg = strtoupper($json['aggregation']['action']) . "(item_historys." . $field . ") AS value";
            $select[] = $agg;
        } else {
            $select = ["SUM(item_historys.quantity) AS value"];
        }

        $sql = "SELECT " . implode(', ', $select) . " FROM item_historys
                LEFT JOIN items ON item_historys.item_id = items.item_id
                LEFT JOIN branches ON item_historys.branch_id = branches.branch_id";

     $filters = $json['filters'] ?? [];
        if (!empty($filters)) {
            $sql .= " WHERE ";
            $where = [];
            foreach ($filters as $filter) {
                $column = match (true) {
                    in_array($filter['column'], ['item_code', 'item_name']) => "items." . $filter['column'],
                    $filter['column'] === 'branch_name' => "branches.branch_name",
                    default =>  $filter['column']
                };

                if ($filter['operator'] === 'between' && is_array($filter['value'])) {
                    $where[] = "$column BETWEEN '{$filter['value'][0]}' AND '{$filter['value'][1]}'";
                } else {
                    $val = is_numeric($filter['value']) ? $filter['value'] : "'{$filter['value']}'";
                    $where[] = "$column {$filter['operator']} $val";
                }
            }
            $sql .= implode(" AND ", $where);
        }

        // if (!empty($userColumns)) {
        //     $groupCols = [];
        //     foreach ($userColumns as $col) {
        //         $groupCols[] = match (true) {
        //             in_array($col, ['item_code', 'item_name']) => "items.$col",
        //             $col === 'branch_name' => "branches.$col",
        //             str_starts_with($col, 'items.') => $col,
        //             str_starts_with($col, 'branches.') => $col,
        //             default => "item_historys.$col"
        //         };
        //     }
        //     $sql .= " GROUP BY " . implode(', ', $groupCols);
        // }

        $results = DB::select($sql);

        if ($json['output'] === 'pdf') {
            $pdf = Pdf::loadView('pdf.generic', [
                'title' => $json['title'] ?? 'Report',
                'data' => $results
            ]);
            return $pdf->download('report.pdf');
        }

        // if ($json['output'] === 'excel') {
        //     return Excel::download(new GenericExport($results), 'report.xlsx');
        // }

        return response()->json([
            'title' => $json['title'] ?? 'Chart',
            'chart_type' => $json['chart_type'] ?? 'bar',
            'data' => $results
        ]);
    }
}
