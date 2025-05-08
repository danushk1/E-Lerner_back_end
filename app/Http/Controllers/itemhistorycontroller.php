<?php

namespace App\Http\Controllers;

use App\Models\item_history;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;


class itemhistorycontroller extends Controller
{

    public function generate(Request $request)
    {
        $query = $request->input('query');

        // 1. System message includes exact table schema to prevent column name mistakes
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
        - item_history_id, external_number, branch_id, location_id, document_number, transaction_date, description, item_id, quantity, free_quantity, batch_number, whole_sale_price, retial_price, expire_date, cost_price, created_at, updated_at
        
        To get `item_Name`, join `items.item_id`  
        To get `branch_name`, join `branches.branch_id`
        
        DO NOT return explanation. ONLY return a valid JSON object.
        EOT;
        

        // 2. Ask OpenAI to convert user query to structured chart instructions
        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $query],
            ]
        ]);

        $openAiData = $openAiResponse->json();
        $message = $openAiData['choices'][0]['message']['content'] ?? null;
dd($message);
        if (!$message) {
            return response()->json(['error' => 'Invalid OpenAI response'], 500);
        }

        try {
            $instructions = json_decode($message, true);
    
            $outputType = $instructions['output'] ?? 'table';
            $chartType = $instructions['chart_type'] ?? 'table';
            $action = $instructions['action'] ?? 'none';
            $field = $instructions['field'] ?? null;
            $groupBy = $instructions['group_by'] ?? null;
            $filters = $instructions['filters'] ?? [];
            $columns = $instructions['columns'] ?? [];
            $reportTitle = $instructions['title'] ?? 'stock_balance_report';
    
            $queryBuilder = DB::table('item_historys')
                ->leftJoin('items', 'item_historys.item_id', '=', 'items.item_id')
                ->leftJoin('branches', 'item_historys.branch_id', '=', 'branches.branch_id');
    
            // Apply filters
            foreach ($filters as $filter) {
                $column = $filter['column'];
                $operator = $filter['operator'];
                $value = $filter['value'];
    
                if ($operator === 'between' && is_array($value)) {
                    $queryBuilder->whereBetween($column, $value);
                } else {
                    $queryBuilder->where($column, $operator, $value);
                }
            }
    
            // Select data for output
            if ($outputType === 'pdf' || $outputType === 'excel') {
                // Select only the requested or default columns
                if (empty($columns)) {
                    $columns = [
                        'transaction_date',
                        'items.item_Name as item_Name',
                        'item_historys.quantity',
                        'branches.branch_name as branch_name',
                        'item_historys.external_number'
                    ];
                } else {
                    $columns = array_map(function ($col) {
                        return match ($col) {
                            'item_Name' => 'items.item_Name as item_Name',
                            'branch_name' => 'branches.branch_name as branch_name',
                            default => "item_historys.$col"
                        };
                    }, $columns);
                }
    
                $queryBuilder->select($columns);
                $results = $queryBuilder->get();
    
                if ($outputType === 'pdf') {
                    $pdf = Pdf::loadView('exports.chart-pdf', [
                        'data' => $results,
                        'title' => $reportTitle
                    ]);
                    return $pdf->download(Str::slug($reportTitle) . '.pdf');
                }
    
                if ($outputType === 'excel') {
                    return Excel::download(new class($results, $reportTitle) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithTitle {
                        protected $data, $title;
                        public function __construct($data, $title) {
                            $this->data = $data;
                            $this->title = $title;
                        }
                        public function collection() {
                            return $this->data;
                        }
                        public function title(): string {
                            return $this->title;
                        }
                    }, Str::slug($reportTitle) . '.xlsx');
                }
            }
    
            // For chart/table data
            if ($action === 'none' && $field === 'quantity') {
                $action = 'sum'; // Default for quantity
            }
    
            if ($action !== 'none' && $field) {
                $select = ($groupBy ? "$groupBy, " : "") . "$action($field) as value";
                $queryBuilder->selectRaw($select);
            } elseif ($field) {
                $queryBuilder->select("$field as value");
            }
    
            if ($groupBy) {
                $queryBuilder->groupBy($groupBy);
            }
    
            $results = $queryBuilder->get();
    
            $formattedData = $results->map(function ($row) use ($groupBy) {
                return [
                    'name' => $groupBy ? $row->$groupBy : '',
                    'value' => $row->value,
                ];
            });
    
            return response()->json([
                'charts' => [
                    [
                        'type' => $chartType,
                        'data' => $formattedData,
                    ]
                ]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process chart query',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}