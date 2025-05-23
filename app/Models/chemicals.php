<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class chemicals extends Model
{
    protected $fillable = [
        'name',
        'usage_description',
        'disease_keywords',
    ];
}
