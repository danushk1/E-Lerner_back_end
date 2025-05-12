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
You are a strict assistant that converts user natural language requests into structured JSON with EXACTLY this format:

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

     $structured = $openAiResponse->json('choices.0.message.content');
        $json = json_decode($structured, true);

        if (!isset($json['action']) || !isset($json['field'])) {
            return response()->json(['error' => 'Invalid response from OpenAI'], 422);
        }

        // Start query
        $query = DB::table('item_historys')
            ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
            ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id');

        // Apply filters
        if (!empty($json['filters'])) {
            foreach ($json['filters'] as $filter) {
                $column = $filter['column'];
                $operator = $filter['operator'];
                $value = $filter['value'];

                $qualified = "item_historys.$column";
                if ($operator === 'between') {
                    $query->whereBetween($qualified, $value);
                } else {
                    $query->where($qualified, $operator, $value);
                }
            }
        }

        // Select + Aggregation
        $field = "item_historys." . $json['field'];
        $aggregation = $json['aggregation']['action'] ?? 'sum';
        $aggAlias = 'value';

        if (!empty($json['group_by'])) {
            $groupBy = "item_historys." . $json['group_by'];
            $query->select($groupBy, DB::raw("$aggregation($field) as $aggAlias"))
                  ->groupBy($groupBy);
        } else {
            $query->select($field, DB::raw("$aggregation($field) as $aggAlias"));
        }

        // Columns (optional)
        if (!empty($json['columns'])) {
            $selects = [];
            foreach ($json['columns'] as $col) {
                $selects[] = "item_historys.$col";
            }
            $query->addSelect($selects);
        }

        try {
            $results = $query->get();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process request',
                'details' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'title' => $json['title'] ?? 'Report',
            'chart_type' => $json['chart_type'] ?? 'bar',
            'data' => $results,
            'colors' => ['#3B82F6', '#EF4444', '#FACC15', '#8B5CF6'] // blue, red, yellow, purple
        ]);
    }
}


