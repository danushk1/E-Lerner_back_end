<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class subject extends Model
{
    use HasFactory;
    protected $table = "subjects";
    protected $primaryKey =  'subject_id';
    protected $fillable = [];
   
    protected static $logOnlyDirty = true;
    
}
