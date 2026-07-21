<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Indexado: busca por nome no PDV/Produtos passou a rodar no
            // banco (ver product_variations.ean_gtin acima, mesmo achado).
            $table->string('name')->index();
            $table->string('type')->default('product');
            $table->boolean('active')->default(true);
            $table->foreignId('unit_id')->constrained();
            $table->string('location')->nullable();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('subcategory_id')->nullable()->constrained();
            $table->foreignId('brand_id')->nullable()->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->json('fiscal_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Postgres não indexa FK automaticamente (diferente do MySQL) —
            // category_id é filtrado em relatórios e na listagem paginada de
            // produtos, por isso ganha índice explícito. Separado do
            // ->constrained() acima: encadear ->constrained()->index() direto
            // bagunça o nome da constraint de FK gerada pelo Laravel.
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
