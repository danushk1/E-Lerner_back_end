<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EmbeddingController extends Controller
{
    public function createSubjectEmbeddings(Request $request)
    {
        // Retrieve all active subjects from the database
        $subjects = DB::table('subjects')
            ->select('subject_id', 'subject_title', 'description', 'subject_grade', 'new_price')
            ->where('subject_status', 1)  // Only active subjects
            ->get();

        // Iterate through each subject
        foreach ($subjects as $subject) {
            $text = "{$subject->subject_title}: {$subject->description}. Grade: {$subject->subject_grade}. Price: {$subject->new_price}";

            // Generate embedding using OpenAI
            $embeddingResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'model' => 'text-embedding-ada-002',
                'input' => $text,
            ]);

            if (!$embeddingResponse->successful()) {
                return response()->json(['error' => 'Embedding failed for subject: ' . $subject->subject_id], 500);
            }

            $embedding = $embeddingResponse['data'][0]['embedding'];  // Get embedding vector

            // Insert the embedding into Qdrant
            $qdrantResponse = Http::post(env('QRDANT_URL') . '/collections/' . env('QRDANT_COLLECTION') . '/points', [
                'points' => [[
                    'id' => $subject->subject_id,
                    'vector' => $embedding,
                    'payload' => [
                        'subject_id' => $subject->subject_id,
                        'title' => $subject->subject_title,
                        'description' => $subject->description,
                        'grade' => $subject->subject_grade,
                        'price' => $subject->new_price,
                    ],
                ]],
            ]);

            if (!$qdrantResponse->successful()) {
                return response()->json(['error' => 'Qdrant insert failed for subject: ' . $subject->subject_id], 500);
            }
        }

        return response()->json(['message' => 'All subject embeddings created successfully']);
    }
}
