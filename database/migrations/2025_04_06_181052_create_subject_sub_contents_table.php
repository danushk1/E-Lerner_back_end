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
        Schema::create('subject_sub_contents', function (Blueprint $table) {
            $table->id('subject_sub_content_id');
            $table->integer('subject_id');
            $table->integer('subject_main_content_id');
            $table->string('sub_title');
            $table->string('path');
            $table->string('time')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_sub_contents');
    }
};
