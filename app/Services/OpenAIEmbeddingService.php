<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OpenAIEmbeddingService
{
    public function getEmbedding(string $text): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            ])->post('https://api.openai.com/v1/embeddings', [
                'input' => $text,
                'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API error: ' . $response->body());
                throw new Exception('Failed to get embedding from OpenAI.');
            }

            return $response->json()['data'][0]['embedding'];
        } catch (Exception $e) {
            Log::error('Embedding error: ' . $e->getMessage());
            throw $e;
        }
    }
}
