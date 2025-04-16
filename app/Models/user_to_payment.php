<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_to_payment extends Model
{
    use HasFactory
;
    protected $table = "user_to_payments";
    protected $primaryKey =  'user_to_payment_id';
    protected $fillable = [];
   
    protected static $logOnlyDirty = true;
}
