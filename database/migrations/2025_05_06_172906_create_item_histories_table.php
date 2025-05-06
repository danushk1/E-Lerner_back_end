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
        Schema::create('item_histories', function (Blueprint $table) {
            $table->string('external_number',200)->nullable();
            $table->integer('branch_id')->nullable();
            $table->integer('location_id')->nullable();
            $table->integer('document_number')->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('description')->nullable();
            $table->integer('item_id')->nullable();
            $table->decimal('quantity',10,2)->nullable();
            $table->decimal('free_quantity')->nullable();
            $table->string('batch_number',200)->nullable();
            $table->decimal('whole_sale_price',10,2)->nullable();
            $table->decimal('retial_price',10,2)->nullable();
            $table->date('expire_date')->nullable();
            $table->decimal('cost_price')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_histories');
    }
};
