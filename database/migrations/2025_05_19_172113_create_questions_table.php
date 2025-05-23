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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
          $table->foreignId('paper_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->text('question');
            $table->json('options')->nullable();
            $table->integer('correct_answer')->nullable();
            $table->integer('marks');
            $table->text('criteria')->nullable();
            $table->text('example_answer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
