<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    {"column": "valid_column_name", "operator": "= | > | < | >= | <= | between", "value": "value or [start, end]"}
  ],
  "columns": ["column1", "column2", "column3"],
  "aggregation": {"action": "sum | avg | count", "field": "valid_column_name"},
  "colors": ["#hex1", "#hex2", ...] | null,
  "xAxisLabel": "X-axis label or null",
  "yAxisLabel": "Y-axis label or null",
  "nameKey": "name field or null",
  "valueKey": "value field or null"
}

Use ONLY these columns from the `item_historys` table:
- item_history_id, external_number, branch_id, location_id, document_number, transaction_date, description, item_id, quantity, free_quantity, batch_number, whole_sale_price, retail_price, expire_date, cost_price, created_at, updated_at

For `item_Name`, join `items.item_id`.
For `branch_name`, join `branches.branch_id`.

Rules:
- For queries requesting a sum of similar items, set `action` to "sum", `field` to "quantity", and `group_by` to "item_id" or "item_Name".
- For date ranges (e.g., "from 2023-01-01 to 2023-12-31"), use a `between` filter on `transaction_date`.
- For branch-specific queries (e.g., "for branch XYZ"), include a filter like `{"column": "branch_name", "operator": "=", "value": "XYZ"}`.
- If colors are specified (e.g., "use colors red, blue"), include them as hex codes in `colors` (e.g., ["#FF0000", "#0000FF"]).
- If no colors are specified, use default colors: ["#8884d8", "#82ca9d", "#ffc658", "#ff7300", "#ff4d4f"].
- If axis labels or name/value keys are specified (e.g., "label x-axis as Category"), set `xAxisLabel`, `yAxisLabel`, `nameKey`, or `valueKey` accordingly.
- If no output type is specified, default to "chart".
- If no chart type is specified for charts, default to "bar".
- If no columns are specified, use default columns: ["transaction_date", "item_Name", "quantity", "branch_name", "external_number"].
- Ensure `filters`, `field`, `group_by`, and `columns` only use the listed columns or `item_Name`, `branch_name`.
- DO NOT use placeholder names like "field_name". Use actual column names from the table.
- Ensure the JSON is valid and contains only the specified fields.
- DO NOT return explanations, only a valid JSON object.
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

        $openAiData = $openAiResponse->json();
        $message = $openAiData['choices'][0]['message']['content'] ?? null;

        if (!$message) {
            return response()->json(['error' => 'Invalid OpenAI response'], 500);
        }

        try {
            $instructions = json_decode($message, true);
            if (!is_array($instructions)) {
                throw new \Exception('Invalid JSON from OpenAI');
            }

            $outputType = $instructions['output'] ?? 'chart';
            $chartType = $instructions['chart_type'] ?? 'bar';
            $action = $instructions['action'] ?? 'none';
            $field = $instructions['field'] ?? null;
            $groupBy = $instructions['group_by'] ?? null;
            $filters = $instructions['filters'] ?? [];
            $columns = $instructions['columns'] ?? [];
            $reportTitle = $instructions['title'] ?? 'Item History Report';
            $aggregation = $instructions['aggregation'] ?? null;
            $colors = $instructions['colors'] ?? ['#8884d8', '#82ca9d', '#ffc658', '#ff7300', '#ff4d4f'];
            $xAxisLabel = $instructions['xAxisLabel'] ?? null;
            $yAxisLabel = $instructions['yAxisLabel'] ?? null;
            $nameKey = $instructions['nameKey'] ?? 'name';
            $valueKey = $instructions['valueKey'] ?? 'value';

            // Valid columns for validation
            $validColumns = [
                'item_history_id', 'external_number', 'branch_id', 'location_id', 'document_number',
                'transaction_date', 'description', 'item_id', 'quantity', 'free_quantity', 'batch_number',
                'whole_sale_price', 'retail_price', 'expire_date', 'cost_price', 'created_at', 'updated_at',
                'item_Name', 'branch_name'
            ];

            // Validate filters
            foreach ($filters as $filter) {
                if (!in_array($filter['column'], $validColumns)) {
                    throw new \Exception("Invalid column in filter: {$filter['column']}");
                }
            }

            // Validate field and group_by
            if ($field && !in_array($field, $validColumns)) {
                throw new \Exception("Invalid field: $field");
            }
            if ($groupBy && !in_array($groupBy, $validColumns)) {
                throw new \Exception("Invalid group_by: $groupBy");
            }

            // Validate columns
            foreach ($columns as $col) {
                if (!in_array($col, $validColumns)) {
                    throw new \Exception("Invalid column: $col");
                }
            }

            $branchDetected = false;
            foreach ($filters as $filter) {
                if ($filter['column'] === 'branch_name' || $filter['column'] === 'branch_id') {
                    $branchDetected = true;
                    Log::info("Branch filter detected in query: {$filter['column']} {$filter['operator']} {$filter['value']}");
                    break;
                }
            }

            $queryBuilder = DB::table('item_historys')
                ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
                ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id');

            foreach ($filters as $filter) {
                $column = match ($filter['column']) {
                    'branch_id' => 'item_historys.branch_id',
                    'item_id' => 'item_historys.item_id',
                    'branch_name' => 'branches.branch_name',
                    'item_Name' => 'items.item_Name',
                    default => "item_historys.{$filter['column']}",
                };

                $operator = $filter['operator'];
                $value = $filter['value'];
                if ($operator === 'between' && is_array($value) && count($value) === 2) {
                    $queryBuilder->whereBetween($column, [$value[0], $value[1]]);
                } else {
                    $queryBuilder->where($column, $operator, $value);
                }
            }

            if (empty($columns)) {
                $columns = [
                    'item_historys.transaction_date as transaction_date',
                    'items.item_Name as item_Name',
                    'item_historys.quantity as quantity',
                    'branches.branch_name as branch_name',
                    'item_historys.external_number as external_number',
                ];
            } else {
                $columns = array_map(function ($col) {
                    return match ($col) {
                        'item_Name' => 'items.item_Name as item_Name',
                        'branch_name' => 'branches.branch_name as branch_name',
                        default => "item_historys.$col as $col",
                    };
                }, $columns);
            }

            if ($action !== 'none' && $field) {
                if ($groupBy) {
                    $queryBuilder->selectRaw("$groupBy as $nameKey, $action($field) as $valueKey")->groupBy($groupBy);
                } else {
                    $queryBuilder->selectRaw("$action($field) as $valueKey");
                }
            } elseif ($aggregation) {
                $queryBuilder->selectRaw("{$aggregation['action']}({$aggregation['field']}) as $valueKey");
                if ($groupBy) {
                    $queryBuilder->selectRaw("$groupBy as $nameKey")->groupBy($groupBy);
                }
            } else {
                $queryBuilder->select($columns);
            }

            $results = $queryBuilder->get();

            $formattedData = $results->map(function ($row) use ($groupBy, $columns, $outputType, $nameKey, $valueKey) {
                $data = [
                    $nameKey => $groupBy ? $row->$nameKey : ($row->transaction_date ?? ''),
                    $valueKey => $row->$valueKey ?? ($row->quantity ?? 0),
                ];

                if ($outputType === 'table') {
                    foreach ($columns as $col) {
                        $colName = Str::afterLast($col, ' as ');
                        $data[$colName] = $row->$colName ?? '';
                    }
                }

                return $data;
            })->toArray();

            if ($chartType === 'pie' || $chartType === 'bar') {
                $formattedData = array_map(function ($item, $index) use ($colors) {
                    $item['fill'] = $colors[$index % count($colors)];
                    return $item;
                }, $formattedData, array_keys($formattedData));
            }

            if ($outputType === 'pdf') {
                $pdf = Pdf::loadView('reports.item_history', ['data' => $formattedData, 'title' => $reportTitle]);
                return response()->streamDownload(function () use ($pdf) {
                    echo $pdf->output();
                }, 'report.pdf');
            } elseif ($outputType === 'excel') {
                return Excel::download(new GenericExport($formattedData, $columns), 'report.xlsx');
            }

            return response()->json([
                'charts' => [
                    [
                        'type' => $chartType,
                        'data' => $formattedData,
                        'title' => $reportTitle,
                        'nameKey' => $nameKey,
                        'valueKey' => $valueKey,
                        'colors' => $colors,
                        'xAxisLabel' => $xAxisLabel,
                        'yAxisLabel' => $yAxisLabel,
                    ],
                ],
                'branch_detected' => $branchDetected ? 'Branch filter applied' : 'No branch filter detected',
            ]);
        } catch (\JsonException $e) {
            return response()->json(['error' => 'Invalid JSON from OpenAI'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to process report', 'details' => $e->getMessage()], 500);
        }
    }
}