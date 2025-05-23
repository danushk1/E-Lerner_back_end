<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Question extends Model
{
    use HasFactory;
    protected $fillable = [
        'paper_id', 'type', 'question', 'options',
        'correct_answer', 'marks', 'criteria', 'example_answer'
    ];
    protected $casts = [
        'options' => 'array',
    ];
    public function paper() {
        return $this->belongsTo(Paper::class);
    }
}