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
        Schema::create('user_to_subjects', function (Blueprint $table) {
            $table->id('user_to_subject_id');
            $table->integer('user_id');
            $table->integer('subject_id');
            $table->string('subject_main_content_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_to_subjects');
    }
};
