<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIEmbeddingService
{
    public function getEmbedding(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])->post('https://api.openai.com/v1/embeddings', [
            'input' => $text,
            'model' => 'gpt-4-turbo',
        ]);

        return $response->json()['data'][0]['embedding'];
    }
}
