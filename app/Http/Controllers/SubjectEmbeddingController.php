<?php

namespace App\Http\Controllers;

use App\Models\subject;
use App\Services\OpenAIEmbeddingService;
use Illuminate\Support\Facades\DB;

class SubjectEmbeddingController extends Controller
{
    public function generate( OpenAIEmbeddingService $embedder)
    {
        $items = DB::table('item_historys')->get();

        foreach ($items as $item) {
            // Choose the appropriate text field to embed (adjust 'description' as needed)
            $text = $item->description ?? '';

            if (trim($text) === '') {
                continue; // Skip empty text
            }

            // Generate embedding
            $embedding = $embedder->getEmbedding($text);

            // Save embedding to DB
            DB::table('item_historys')
                ->where('item_history_id', $item->item_history_id)
                ->update(['embedding' => json_encode($embedding)]);
        }

        return response()->json(['message' => 'Embedding saved successfully']);
    }
}
