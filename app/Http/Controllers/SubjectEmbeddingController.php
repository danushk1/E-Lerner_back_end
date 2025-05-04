<?php

namespace App\Http\Controllers;

use App\Models\subject;
use App\Services\OpenAIEmbeddingService;

class SubjectEmbeddingController extends Controller
{
    public function generate($id, OpenAIEmbeddingService $embedder)
    {
        $subject = subject::findOrFail($id);
        $text = $subject->subject_title . ' ' . $subject->description . ' ' . $subject->subject_grade . ' ' . $subject->new_price;
        $embedding = $embedder->getEmbedding($text);

        $subject->embedding = json_encode($embedding);
        $subject->save();

        return response()->json(['message' => 'Embedding saved successfully']);
    }
}
