<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_to_payment_installment extends Model
{
    use HasFactory
;
    protected $table = "user_to_payment_installments";
    protected $primaryKey =  'user_to_payment_installment_id';
    protected $fillable = [];
   
    protected static $logOnlyDirty = true;
}
