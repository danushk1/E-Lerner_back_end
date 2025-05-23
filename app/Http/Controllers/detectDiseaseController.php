<?php

namespace App\Http\Controllers;

use App\Models\chemicals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class detectDiseaseController extends Controller
{
    public function detect(Request $request)
{
    $image = $request->file('image');

    // Convert image to base64
    $base64Image = base64_encode(file_get_contents($image));

    // Call OpenAI Vision API
    $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
        "model" => "gpt-4-vision-preview",
        "messages" => [
            [
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => "What disease does this plant image show? Also, provide treatment instructions."],
                    ["type" => "image_url", "image_url" => ["url" => "data:image/jpeg;base64," . $base64Image]]
                ],
            ],
        ],
        "max_tokens" => 1000,
    ]);

    $content = $response['choices'][0]['message']['content'];

    // Extract disease name from response (simplified)
    preg_match('/(?i)disease(?: is|:)? ([^\n]+)/', $content, $matches);
    $diseaseName = $matches[1] ?? 'unknown';

    // Match chemicals
    $chemicals = chemicals::where('disease_keywords', 'like', '%' . strtolower($diseaseName) . '%')->get();

    return response()->json([
        'disease_name' => $diseaseName,
        'treatment_info' => $content,
        'recommended_chemicals' => $chemicals
    ]);
}

}
