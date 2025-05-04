<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subject;
use App\Services\OpenAIEmbeddingService;

class SubjectSearchController extends Controller
{
    public function search(Request $request, OpenAIEmbeddingService $embedder)
    {
        $query = $request->input('query');
        $queryEmbedding = $embedder->getEmbedding($query);

        $results = [];

        foreach (Subject::whereNotNull('embedding')->get() as $subject) {
            $subjectEmbedding = json_decode($subject->embedding, true);
            $score = $this->cosineSimilarity($queryEmbedding, $subjectEmbedding);

            $results[] = [
                'subject' => $subject,
                'score' => $score
            ];
        }

        // Sort descending by similarity
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return response()->json(array_slice($results, 0, 5)); // top 5 results
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0; $magA = 0; $magB = 0;
        foreach ($a as $i => $val) {
            $dot += $val * $b[$i];
            $magA += $val ** 2;
            $magB += $b[$i] ** 2;
        }
        return $dot / (sqrt($magA) * sqrt($magB));
    }
}
