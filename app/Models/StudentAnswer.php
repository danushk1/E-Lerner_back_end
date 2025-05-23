<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $fillable = [
        'paper_id', 'student_id', 'question_id',
        'answer', 'marks_obtained', 'feedback', 'submitted_at'
    ];

    protected $casts = [
        'feedback' => 'array',
        'submitted_at' => 'datetime'
    ];

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

   
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}