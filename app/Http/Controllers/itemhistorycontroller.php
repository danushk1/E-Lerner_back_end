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

             $outputType = $instructions['output'] ?? 'table';
            $chartType = $instructions['chart_type'] ?? 'pie';
            $filters = $instructions['filters'] ?? [];
            $columns = $instructions['columns'] ?? [];
            $aggregation = $instructions['aggregation'] ?? null;
            $groupBy = $instructions['group_by'] ?? null;

            // STEP 3: Build Query
$queryBuilder = DB::table('item_historys')
    ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
    ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id');

if ($aggregation && isset($aggregation['action']) && isset($aggregation['field'])) {
    $aggAction = $aggregation['action'];
    $aggField = $aggregation['field'];
    $aggFieldFull = "item_historys.$aggField";  // Ensure field comes from item_historys table

    // Fix for ambiguity: Explicitly use item_historys.item_id in the group by and select statements
    $queryBuilder->selectRaw("$aggAction($aggFieldFull) as value, item_historys.item_id");

    // If group_by is specified, add it properly
    if ($groupBy) {
        $groupByFull = "item_historys.$groupBy";  // Ensure group by is explicitly item_historys
        $queryBuilder->groupBy($groupByFull);  // Fix: Ensure we group only by item_historys.item_id
    } else {
        // Default to grouping by item_id if no group_by specified
        $queryBuilder->groupBy('item_historys.item_id');
    }
}


            // Filters
          if ($aggregation && isset($aggregation['action']) && isset($aggregation['field'])) {
                $aggAction = $aggregation['action'];
                $aggField = $aggregation['field'];
                $aggFieldFull = "item_historys.$aggField";  // Fix for ambiguous column error

                // Fixing the query to resolve column ambiguity
                $queryBuilder->selectRaw("$aggAction($aggFieldFull) as value");

                // If group_by is specified, add it properly
                if ($groupBy) {
                    $groupByFull = "item_historys.$groupBy";  // Fix for ambiguous column error
                    $queryBuilder->addSelect($groupByFull)->groupBy($groupByFull);
                }
            }
           if (empty($columns)) {
                $columns = ['item_historys.transaction_date', 'items.item_Name', 'branches.branch_name', 'item_historys.quantity'];
            }

            // Select the columns
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
                // Return the chart data to frontend
                return response()->json([
                    'charts' => [
                        [
                            'type' => $chartType,
                            'data' => $formattedData,
                            'colors' => ['#0000FF', '#FF0000', '#FFFF00', '#800080'], // Set pie chart colors (blue, red, yellow, purple)
                        ]
                    ]
                ]);
            } elseif ($outputType === 'table') {
                // Return the table data to frontend
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
