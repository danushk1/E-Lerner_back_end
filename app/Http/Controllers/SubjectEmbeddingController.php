<?php

namespace App\Http\Controllers;

use App\Models\item_historys;
use App\Models\subject;
use App\Services\OpenAIEmbeddingService;

class SubjectEmbeddingController extends Controller
{
    public function generate($id, OpenAIEmbeddingService $embedder)
    {
        $items = item_historys::all();
        foreach ($items as $item) {
            $text = $item->description ?? ''; // ✅ Adjust this to your actual text field

            if (trim($text) === '') {
                continue; // Skip empty descriptions
            }

            // Get the embedding from OpenAI
            $embedding = $embedder->getEmbedding($text);

            // Save embedding to this row
            $item->embedding = json_encode($embedding);
            $item->save();
        }

        return response()->json(['message' => 'Embedding saved successfully']);
    }
}
