<?php

use App\Events\NotificationEvent;
use App\Http\Controllers\Api\OpenAIController;
use App\Http\Controllers\ChartAssistantController;
use App\Http\Controllers\EmbeddingController;
use App\Http\Controllers\itemhistorycontroller;
use App\Http\Controllers\MyCoursessController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubjectEmbeddingController;
use App\Http\Controllers\SubjectSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::get('/getall_subject',[SubjectController::class,'getAllSubject']);
Route::get('/get_subject/{id}',[SubjectController::class,'getSubject']);
Route::middleware('clerk.auth')->group(function () {

    Route::post('/enroll_course', [SubjectController::class, 'enrollCourse']);
    Route::get('/my_courses/{user_id}', [MyCoursessController::class, 'my_courses']);
    Route::get('/subject_main_content/{user_id}/{subject_id}', [MyCoursessController::class,'subject_main_content']);
    Route::post('/send_enrollment_notification', function (Request $request) {

        try {

          broadcast(new NotificationEvent($request->all()))->toOthers();
        } catch (\Exception $e) {
         Log::error('Error broadcasting event: ' . $e->getMessage());
        }
         
      });
});
Route::post('/chat', [OpenAIController::class, 'chat']);
Route::post('/embeddings/subjects', [EmbeddingController::class, 'createSubjectEmbeddings']);
Route::get('/embedding/subject', [SubjectEmbeddingController::class, 'generate']);
Route::post('/search/subjects', [SubjectSearchController::class, 'search']);
//Route::post('/chart/generate', [ChartAssistantController::class, 'generate']);
Route::post('/chart/generate', [itemhistorycontroller::class, 'generate']);