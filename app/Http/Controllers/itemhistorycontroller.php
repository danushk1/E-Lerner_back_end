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
        You are a chart assistant. Convert the user's request into a JSON object with this format:
        {
          "output": "chart | pdf | excel | table",
          "chart_type": "bar | line | pie | scatter | table",
          "action": "sum | count | avg | max | min | none",
          "field": "column_to_aggregate",
          "group_by": "column_name_or_null",
          "filters": [
            {"column": "field_name", "operator": "= | > | < | >= | <= | between", "value": "value or [start, end]"}
          ]
        }
        
        The database tables are:
        
        **item_historys**
        - item_history_id (int)
        - external_number (string)
        - branch_id (int)
        - location_id (int)
        - document_number (int)
        - transaction_date (date)
        - description (string)
        - item_id (int)
        - quantity (decimal)
        - free_quantity (decimal)
        - batch_number (string)
        - whole_sale_price (decimal)
        - retial_price (decimal)
        - expire_date (date)
        - cost_price (decimal)
        - created_at (timestamp)
        - updated_at (timestamp)
        
        **items**
        - item_id (int)
        - item_Name (string)
        
        **branches**
        - branch_id (int)
        - branch_name (string)
        
        You are allowed to join `item_historys` with `items` using `item_historys.item_id = items.item_id` and with `branches` using `item_historys.branch_id = branches.branch_id`.
        
        Use only these columns. Do not invent any column names.
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

        if (!$message) {
            return response()->json(['error' => 'Invalid OpenAI response'], 500);
        }

        try {
            $instructions = json_decode($message, true);
            $outputType = $instructions['output'] ?? 'chart';
            $chartType = $instructions['chart_type'] ?? 'table';
            $action = $instructions['action'] ?? 'none';
            $field = $instructions['field'] ?? null;
            $groupBy = $instructions['group_by'] ?? null;
            $filters = $instructions['filters'] ?? [];

            $queryBuilder = DB::table('item_historys');

            // Check if joins are needed
            $joinItems = false;
            $joinBranches = false;

            $allFields = [$field, $groupBy];
            foreach ($filters as $filter) {
                $allFields[] = $filter['column'];
            }

            foreach ($allFields as $col) {
                if (in_array($col, ['item_Name'])) $joinItems = true;
                if (in_array($col, ['branch_name'])) $joinBranches = true;
            }

            if ($joinItems) {
                $queryBuilder->join('items', 'item_historys.item_id', '=', 'items.item_id');
            }

            if ($joinBranches) {
                $queryBuilder->join('branches', 'item_historys.branch_id', '=', 'branches.branch_id');
            }

            // Resolve column references
            $selectField = $field;
            $selectGroup = $groupBy;

            if ($field === 'item_Name') $selectField = 'items.item_Name';
            if ($field === 'branch_name') $selectField = 'branches.branch_name';

            if ($groupBy === 'item_Name') $selectGroup = 'items.item_Name';
            if ($groupBy === 'branch_name') $selectGroup = 'branches.branch_name';

            // Apply filters
            foreach ($filters as $filter) {
                $column = $filter['column'];
                $operator = $filter['operator'];
                $value = $filter['value'];

                if ($column === 'item_Name') $column = 'items.item_Name';
                if ($column === 'branch_name') $column = 'branches.branch_name';

                if ($operator === 'between' && is_array($value)) {
                    $queryBuilder->whereBetween($column, $value);
                } else {
                    $queryBuilder->where($column, $operator, $value);
                }
            }

            // Select and group
            if ($action !== 'none' && $field) {
                $select = ($groupBy ? "$selectGroup, " : "") . "$action($selectField) as value";
                $queryBuilder->selectRaw($select);
            } elseif ($field) {
                $queryBuilder->select("$selectField as value");
            }

            if ($groupBy) {
                $queryBuilder->groupBy($selectGroup);
            }

            $results = $queryBuilder->get();

            if ($outputType === 'pdf') {
                $pdf = Pdf::loadView('exports.chart-pdf', ['data' => $results]);
                return $pdf->download('chart.pdf');
            }

            if ($outputType === 'excel') {
                $filename = 'chart_export_' . now()->format('Ymd_His') . '.xlsx';
                return Excel::download(new class($results) implements \Maatwebsite\Excel\Concerns\FromCollection {
                    protected $data;
                    public function __construct($data) { $this->data = $data; }
                    public function collection() { return new Collection($this->data); }
                }, $filename);
            }

            // Default: chart JSON
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