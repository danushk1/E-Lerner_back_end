<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Paper extends Model
{
   use HasFactory;
    protected $fillable = ['name', 'code', 'grade'];
    public function questions() {
        return $this->hasMany(Question::class);
    }
}