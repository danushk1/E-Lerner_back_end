<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;
use Illuminate\Support\Arr;

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
          "columns": ["field1", "field2", "..."]
        }
        
        Use only these columns from the `item_historys` table:
        - item_history_id, external_number, branch_id, location_id, document_number, transaction_date, description, item_id, quantity, free_quantity, batch_number, whole_sale_price, retail_price, expire_date, cost_price, created_at, updated_at
        
        To get `item_Name`, join `items.item_id`
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
            \Log::error('OpenAI API failed', ['status' => $openAiResponse->status(), 'body' => $openAiResponse->body()]);
            return response()->json(['error' => 'Failed to connect to OpenAI API'], 503);
        }

        $openAiData = $openAiResponse->json();
        $message = $openAiData['choices'][0]['message']['content'] ?? null;

        if (!$message) {
            \Log::error('Invalid OpenAI response', ['response' => $openAiData]);
            return response()->json(['error' => 'Invalid OpenAI response'], 500);
        }

        \Log::info('OpenAI Response:', [$message]);

        try {
            $instructions = json_decode($message, true);
            if (!is_array($instructions)) {
                throw new \Exception('Invalid JSON from OpenAI: Response is not an array');
            }

            $outputType = $instructions['output'] ?? 'table';
            $chartType = $instructions['chart_type'] ?? 'table';
            $action = $instructions['action'] ?? 'none';
            $field = $instructions['field'] ?? null;
            $groupBy = $instructions['group_by'] ?? null;
            $filters = $instructions['filters'] ?? [];
            $columns = $instructions['columns'] ?? [];
            $reportTitle = $instructions['title'] ?? 'Stock Balance Report';

            $validColumns = [
                'item_history_id', 'external_number', 'branch_id', 'location_id', 'document_number',
                'transaction_date', 'description', 'item_id', 'quantity', 'free_quantity',
                'batch_number', 'whole_sale_price', 'retail_price', 'expire_date', 'cost_price',
                'created_at', 'updated_at', 'item_Name', 'branch_name'
            ];

            $columns = array_filter($columns, fn($col) => in_array($col, $validColumns));
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

            \Log::info('Processed Columns:', $columns);

            $queryBuilder = DB::table('item_historys')
                ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
                ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id');

            foreach ($filters as $filter) {
                $column = $filter['column'];
                $operator = $filter['operator'];
                $value = $filter['value'];

                if ($operator === 'between' && is_array($value) && count($value) === 2) {
                    $queryBuilder->whereBetween($column, $value);
                } else {
                    $queryBuilder->where($column, $operator, $value);
                }
            }

            \Log::info('Filters Applied:', $filters);

            if (in_array($outputType, ['pdf', 'excel'])) {
                $queryBuilder->select(array_map(fn($col) => str_contains($col, ' as ') ? $col : "$col as " . Arr::last(explode('.', $col)), $columns));
                DB::enableQueryLog();
                $results = $queryBuilder->get();
                \Log::info('SQL Query:', DB::getQueryLog());
                \Log::info('Query Results Count:', ['count' => $results->count()]);

                if ($results->isEmpty()) {
                    \Log::warning('No data found for query', ['filters' => $filters, 'columns' => $columns]);
                    return response()->json(['error' => 'No data found for the requested report'], 404);
                }

                if ($outputType === 'pdf') {
                    \Log::info('Generating PDF with columns:', array_map(fn($col) => Arr::last(explode(' as ', $col)), $columns));
                    $pdf = PDF::loadView('exports.chart-pdf', [
                        'data' => $results,
                        'title' => $reportTitle,
                        'columns' => array_map(fn($col) => Arr::last(explode(' as ', $col)), $columns),
                    ]);

                    return response($pdf->output(), 200)
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', 'attachment; filename="' . Str::slug($reportTitle) . '.pdf"');
                }

                // if ($outputType === 'excel') {
                //     \Log::info('Generating Excel with columns:', array_map(fn($col) => Arr::last(explode(' as ', $col)), $columns));
                //     return Excel::download(
                //         new GenericExport($results, $reportTitle, array_map(fn($col) => Arr::last(explode(' as ', $col)), $columns)),
                //         Str::slug($reportTitle) . '.xlsx'
                //     );
                // }
            }

            if ($action !== 'none' && $field) {
                $select = ($groupBy ? 
                "$groupBy, " : "") . "$action($field) as value";
                $queryBuilder->selectRaw($select);
            } elseif ($field) {
                $queryBuilder->selectRaw("$field as value");
            } else {
                $queryBuilder->select($columns);
            }

            if ($groupBy) {
                $queryBuilder->groupBy($groupBy);
            }

            DB::enableQueryLog();
            $results = $queryBuilder->get();
            \Log::info('SQL Query:', DB::getQueryLog());
            \Log::info('Query Results Count:', ['count' => $results->count()]);

            $formattedData = $results->map(function ($row) use ($groupBy, $columns, $outputType) {
                $data = [
                    'name' => $groupBy ? $row->$groupBy : ($row->transaction_date ?? ''),
                    'value' => $row->value ?? ($row->quantity ?? 0),
                ];

                if ($outputType === 'table') {
                    foreach ($columns as $col) {
                        $colName = Arr::last(explode(' as ', $col));
                        $data[$colName] = $row->$colName ?? '';
                    }
                }

                return $data;
            })->toArray();

            if ($chartType === 'pie') {
                $colors = ['#8884d8', '#82ca9d', '#ffc658', '#ff7300', '#ff4d4f'];
                $formattedData = array_map(function ($item, $index) use ($colors) {
                    $item['fill'] = $colors[$index % count($colors)];
                    return $item;
                }, $formattedData, array_keys($formattedData));
            }

            return response()->json([
                'charts' => [
                    [
                        'type' => $chartType,
                        'data' => $formattedData,
                        'title' => $reportTitle,
                    ],
                ],
            ]);
        } catch (\JsonException $e) {
            \Log::error('JSON Decode Error:', ['error' => $e->getMessage(), 'response' => $message]);
            return response()->json(['error' => 'Invalid JSON from OpenAI', 'details' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            \Log::error('Report processing failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to process report', 'details' => $e->getMessage()], 500);
        }
    }
}