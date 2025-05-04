<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\OpenAIEmbeddingService;

class SubjectEmbeddingController extends Controller
{
    public function generate($id, OpenAIEmbeddingService $embedder)
    {
        $subject = Subject::findOrFail($id);
        $text = $subject->subject_title . ' ' . $subject->description;
        $embedding = $embedder->getEmbedding($text);

        $subject->embedding = json_encode($embedding);
        $subject->save();

        return response()->json(['message' => 'Embedding saved successfully']);
    }
}
