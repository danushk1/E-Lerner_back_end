<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_to_payments', function (Blueprint $table) {
            $table->id('user_to_payment_id');
            $table->integer('user_id');
            $table->string('name');  
            $table->string('phone_number');
            $table->string('slip');
            $table->integer('subject_id');
            $table->decimal('subject_price', 8, 2)->nullable();
            $table->string('payment_type');
            $table->decimal('remaining_amount', 8, 2)->nullable();
            $table->boolean('status')->default(0);
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_to_payments');
    }
};
