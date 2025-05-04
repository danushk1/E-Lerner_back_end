<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIEmbeddingService
{
    public function getEmbedding(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.openai.com/v1/embeddings', [
            'input' => $text,
            'model' => 'gpt-4-turbo',
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI Error: ' . $response->body());
            throw new \Exception('Failed to get embedding from OpenAI.');
        }

        $json = $response->json();

        if (!isset($json['data'][0]['embedding'])) {
            Log::error('Embedding missing: ' . json_encode($json));
            throw new \Exception('Invalid embedding response.');
        }

        return $json['data'][0]['embedding'];
    }
}
