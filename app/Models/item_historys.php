<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class item_historys extends Model
{
    use HasFactory;
    protected $table = "item_historys";
    protected $primaryKey =  'item_history_id';
    protected $fillable = [];
   
    protected static $logOnlyDirty = true;
    
}
