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

        if ($openAiResponse->failed()) {
            return response()->json(['error' => 'OpenAI API failed'], 500);
        }

        $content = $openAiResponse['choices'][0]['message']['content'] ?? null;

        try {
            $instructions = json_decode($content, true);
            if (!is_array($instructions)) {
                throw new \Exception('Invalid JSON returned by OpenAI.');
            }

            $output = $instructions['output'] ?? 'chart';
            $chartType = $instructions['chart_type'] ?? 'bar';
            $title = $instructions['title'] ?? 'Report';
            $groupBy = $instructions['group_by'] ?? null;
            $aggregation = $instructions['aggregation'] ?? null;
            $columns = $instructions['columns'] ?? [];
            $filters = $instructions['filters'] ?? [];

            // STEP 3: Build Query
            $query = DB::table('item_historys')
                ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
                ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id');

            // Filters
            foreach ($filters as $filter) {
                $col = $filter['column'];
                $op = $filter['operator'];
                $val = $filter['value'];

                if (is_array($val) && strtolower($op) === 'between') {
                    $query->whereBetween($col, $val);
                } else {
                    $query->where($col, $op, $val);
                }
            }
//q
            // Aggregation
            if ($aggregation && isset($aggregation['action'], $aggregation['field'])) {
                $aggAction = $aggregation['action'];
                $aggField = $aggregation['field'];
                $query->selectRaw("$aggAction($aggField) as value");

                if ($groupBy) {
                    $query->addSelect($groupBy)->groupBy($groupBy);
                }
            } elseif (!empty($columns)) {
                $query->select($columns);
            } else {
                $query->select('item_historys.*');
            }

            $data = $query->get();

            // STEP 4: Format Data
            $formatted = $data->map(function ($row) {
                return (array) $row;
            })->toArray();

            // STEP 5: Return Output
            if ($output === 'chart') {
                return response()->json([
                    'charts' => [[
                        'type' => $chartType,
                        'title' => $title,
                        'data' => $formatted,
                        'nameKey' => $groupBy ?? 'label',
                        'valueKey' => $aggregation['field'] ?? 'value',
                        'colors' => ['#8884d8', '#82ca9d', '#ffc658', '#ff7300', '#ff4d4f']
                    ]]
                ]);
            }

            if ($output === 'pdf') {
                $pdf = Pdf::loadView('reports.pdf', ['title' => $title, 'data' => $formatted]);
                return response($pdf->output(), 200)->header('Content-Type', 'application/pdf');
            }

            // if ($output === 'excel') {
            //     return Excel::download(new GenericExport($formatted), 'report.xlsx');
            // }

            return response()->json(['table' => $formatted]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error processing request.',
                'details' => $e->getMessage(),
                'raw' => $content,
            ], 500);
        }
    }

}
