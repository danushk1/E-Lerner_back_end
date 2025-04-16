<?php

use App\Http\Controllers\SubjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::get('/getall_subject',[SubjectController::class,'getAllSubject']);
Route::get('/get_subject/{id}',[SubjectController::class,'getSubject']);
Route::middleware('clerk.auth')->group(function () {

    Route::post('/enroll_course', [SubjectController::class, 'enrollCourse']);


});
