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

try {
        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $userQuery],
            ],

        ]);

        $parsed = json_decode($openAiResponse['choices'][0]['message']['content'], true);

            if (!$parsed || !isset($parsed['action'], $parsed['field'], $parsed['group_by'])) {
                return response()->json(['error' => 'Invalid structure from AI', 'raw' => $openAiResponse['choices'][0]['message']['content']], 422);
            }

           $query = DB::table('item_historys')
                ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
                ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id');

            // Apply filters if available
            if (!empty($parsed['filters'])) {
                foreach ($parsed['filters'] as $filter) {
                    $table = $this->resolveTableForColumn($filter['field']);
                    $column = "$table.{$filter['field']}";
                    $operator = $filter['operator'] ?? '=';
                    $value = $filter['value'];
                    $query->where($column, $operator, $value);
                }
            }

            // Select & group
            $aggField = $this->qualifyColumn($parsed['field']);
            $groupBy = $this->qualifyColumn($parsed['group_by']);

            $query->select(
                DB::raw(strtoupper($parsed['action']) . "($aggField) as value"),
                $groupBy . ' as label'
            )->groupBy($groupBy);

            $data = $query->get();

            return response()->json([
                'type' => $parsed['chart_type'] ?? 'bar',
                'title' => $parsed['title'] ?? 'Chart Result',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process request',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function resolveTableForColumn($column)
    {
        $itemHistorys = ['item_id', 'branch_id', 'transaction_date', 'quantity', 'free_quantity'];
        $items = ['item_name', 'item_code', 'cost_price'];
        $branches = ['branch_name', 'location_id'];

        if (in_array($column, $itemHistorys)) return 'item_historys';
        if (in_array($column, $items)) return 'items';
        if (in_array($column, $branches)) return 'branches';

        return 'item_historys'; // fallback
    }

    private function qualifyColumn($column)
    {
        return $this->resolveTableForColumn($column) . '.' . $column;
    }
}


