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
        Schema::create('user_to_payment_installments', function (Blueprint $table) {
            $table->id('user_to_payment_installment_id');
            $table->integer('user_to_payment_id');
            $table->integer('user_id');
            $table->integer('subject_id');
            $table->string('installment_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_to_payment_installments');
    }
};
