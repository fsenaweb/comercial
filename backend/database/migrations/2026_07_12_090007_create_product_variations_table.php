<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('ean_gtin')->nullable();
            $table->string('product_code')->unique();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('markup', 7, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->integer('current_quantity')->default(0);
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
