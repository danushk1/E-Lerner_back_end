<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;

class ItemHistoryController extends Controller
{
    private const DEFAULT_COLUMNS = [
        'item_historys.transaction_date as transaction_date',
        'items.item_Name as item_Name',
        'item_historys.quantity as quantity',
        'branches.branch_name as branch_name',
        'item_historys.external_number as external_number',
    ];

    private const VALID_COLUMNS = [
        'item_history_id', 'external_number', 'branch_id', 'location_id', 'document_number',
        'transaction_date', 'description', 'item_id', 'quantity', 'free_quantity',
        'batch_number', 'whole_sale_price', 'retail_price', 'expire_date', 'cost_price',
        'created_at', 'updated_at', 'item_Name', 'branch_name'
    ];

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
  "columns": ["field1", "field2", "..."]
}

Use only these columns from the `item_historys` table:
- item_history_id, external_number, branch_id, location_id, document_number, transaction_date, description, item_id, quantity, free_quantity, batch_number, whole_sale_price, retail_price, expire_date, cost_price, created_at, updated_at

To get `item_Name`, join `items.item_id`
To get `branch_name`, join `branches.branch_id`

DO NOT return explanation. ONLY return a valid JSON object.
EOT;

        $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $query]
            ]
        ]);

        if ($response->failed()) {
            \Log::error('OpenAI API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query
            ]);
            return response()->json(['error' => 'Failed to connect to OpenAI API'], 503);
        }

        $openAiData = $response->json();
        $message = $openAiData['choices'][0]['message']['content'] ?? null;

        if (!$message) {
            \Log::error('Invalid OpenAI response', ['response' => $openAiData]);
            return response()->json(['error' => 'Invalid OpenAI response'], 500);
        }

        try {
            $instructions = json_decode($message, true);
            if (!is_array($instructions)) {
                throw new \Exception('Invalid JSON from OpenAI: Not an array');
            }

            $outputType = $instructions['output'] ?? 'table';
            $chartType = $instructions['chart_type'] ?? 'table';
            $action = $instructions['action'] ?? 'none';
            $field = $instructions['field'] ?? null;
            $groupBy = $instructions['group_by'] ?? null;
            $filters = $instructions['filters'] ?? [];
            $columns = $instructions['columns'] ?? [];
            $reportTitle = $instructions['title'] ?? 'Stock Balance Report';

            // Validate and prepare columns
            $columns = array_filter($columns, fn($col) => in_array($col, self::VALID_COLUMNS));
            if (empty($columns)) {
                $columns = self::DEFAULT_COLUMNS;
            } else {
                $columns = array_map(function ($col) {
                    return match ($col) {
                        'item_Name' => 'items.item_Name as item_Name',
                        'branch_name' => 'branches.branch_name as branch_name',
                        default => "item_historys.$col as $col",
                    };
                }, $columns);
            }

            $queryBuilder = DB::table('item_historys')
                ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
                ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id')
                ->select($columns);

            // Apply filters
            foreach ($filters as $filter) {
                $column = $filter['column'];
                $operator = $filter['operator'];
                $value = $filter['value'];

                if (!in_array($column, self::VALID_COLUMNS)) {
                    throw new \Exception("Invalid filter column: $column");
                }

                $col = match ($column) {
                    'item_Name' => 'items.item_Name',
                    'branch_name' => 'branches.branch_name',
                    default => "item_historys.$column"
                };

                if ($operator === 'between' && is_array($value) && count($value) === 2) {
                    $queryBuilder->whereBetween($col, $value);
                } else {
                    $queryBuilder->where($col, $operator, $value);
                }
            }

            // Apply groupBy
            if ($groupBy && in_array($groupBy, self::VALID_COLUMNS)) {
                $groupCol = match ($groupBy) {
                    'item_Name' => 'items.item_Name',
                    'branch_name' => 'branches.branch_name',
                    default => "item_historys.$groupBy"
                };
                $queryBuilder->groupBy(DB::raw($groupCol));
            }

            // Apply aggregation
            if ($action !== 'none' && $field && in_array($field, self::VALID_COLUMNS)) {
                $fieldCol = match ($field) {
                    'item_Name' => 'items.item_Name',
                    'branch_name' => 'branches.branch_name',
                    default => "item_historys.$field"
                };

                $queryBuilder->addSelect(DB::raw(strtoupper($action) . "($fieldCol) as value"));

                if ($groupBy) {
                    $groupCol = match ($groupBy) {
                        'item_Name' => 'items.item_Name',
                        'branch_name' => 'branches.branch_name',
                        default => "item_historys.$groupBy"
                    };
                    $queryBuilder->addSelect(DB::raw("$groupCol as name"));
                }
            }

            \Log::info('Generated SQL Query', [
                'query' => $queryBuilder->toSql(),
                'bindings' => $queryBuilder->getBindings()
            ]);

            $data = $queryBuilder->get()->map(fn($row) => (array) $row)->toArray();

            // Output handling
            if ($outputType === 'chart') {
                return response()->json([
                    'charts' => [[
                        'type' => $chartType,
                        'data' => $data,
                        'title' => $reportTitle
                    ]]
                ]);
            } elseif ($outputType === 'pdf') {
                $pdf = PDF::loadView('reports.generic', ['data' => $data, 'title' => $reportTitle]);
                return $pdf->download('report.pdf');
            } elseif ($outputType === 'excel') {
                return Excel::download(new GenericExport($data), 'report.xlsx');
            } else {
                return response()->json(['data' => $data, 'title' => $reportTitle]);
            }

        } catch (\Exception $e) {
            \Log::error('Error processing report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response' => $message,
                'query' => $query
            ]);
            return response()->json(['error' => 'Failed to process report: ' . $e->getMessage()], 500);
        }
    }
}
