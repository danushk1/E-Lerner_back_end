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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id('subject_id');
            $table->string('subject_name');
            $table->string('subject_code');
            $table->string('subject_type');
            $table->string('subject_grade');
            $table->string('subject_title');
            $table->string('description')->nullable();
            $table->decimal('old_price', 8, 2)->default(0);
            $table->decimal('new_price', 8, 2)->default(0);
            $table->string('subject_image')->nullable();
            $table->integer('rating')->nullable();
            $table->integer('payment_duration')->nullable();
            $table->integer('payment')->nullable();
            $table->integer('subject_status')->default(1);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
