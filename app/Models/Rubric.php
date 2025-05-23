<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rubric extends Model
{
    protected $fillable = [
        'question_id', 'keywords', 'model_answer'
    ];

    protected $casts = [
        'keywords' => 'array'
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}