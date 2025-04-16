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
        Schema::create('subject_main_contents', function (Blueprint $table) {
            $table->id('subject_main_content_id');
            $table->integer('subject_id');
            $table->string('time');
            $table->string('main_title');
            $table->string('count');
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_main_contents');
    }
};
