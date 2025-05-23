<?php
namespace App\Http\Controllers;
use App\Models\Paper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaperController extends Controller {
    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:papers',
            'grade' => 'required|integer|min:1|max:13',
            'questions' => 'required|array',
            'questions.*.type' => 'required|in:mcq,short_answer,structured,essay',
            'questions.*.question' => 'required|string',
            'questions.*.options' => 'nullable|array|required_if:type,mcq',
            'questions.*.correct_answer' => 'nullable|integer|required_if:type,mcq',
            'questions.*.marks' => 'required|integer|min:1',
            'questions.*.criteria' => 'nullable|string|required_if:type,structured,essay',
            'questions.*.example_answer' => 'nullable|string|required_if:type,short_answer,structured,essay',
        ]);

        try {
            DB::beginTransaction();
            $paper = Paper::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'grade' => $validated['grade'],
            ]);

            foreach ($validated['questions'] as $questionData) {
                $paper->questions()->create([
                    'type' => $questionData['type'],
                    'question' => $questionData['question'],
                    'options' => $questionData['options'] ?? null,
                    'correct_answer' => $questionData['correct_answer'] ?? null,
                    'marks' => $questionData['marks'],
                    'criteria' => $questionData['criteria'] ?? null,
                    'example_answer' => $questionData['example_answer'] ?? null,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Paper saved successfully', 'paper' => $paper->load('questions')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save paper', 'error' => $e->getMessage()], 500);
        }
    }

    public function index() {
        $papers = Paper::with('questions')->get();
        return response()->json($papers);
    }
}