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
            // Indexado: leitura de código de barras no PDV/Etiquetas vira uma
            // busca indexada no banco (GET /product-variations/lookup) em vez
            // de carregar o catálogo inteiro no navegador — achado real ao
            // testar a importação do sistema legado (13 mil produtos
            // estourando memória do PHP), ver docs/11-migracao-sistema-legado.md.
            $table->string('ean_gtin')->nullable()->index();
            $table->string('product_code')->unique();
            // Segundo código do produto, opcional e sem exigir unicidade —
            // na importação do sistema legado recebe o CODIGO interno
            // original (product_code recebe a REFERENCIA, que tem
            // prioridade); em cadastros novos, campo livre pra quem quiser
            // manter um código interno/secundário além do product_code
            // principal. Decisão do usuário em 2026-07-18, ver
            // docs/11-migracao-sistema-legado.md.
            $table->string('legacy_code')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('markup', 7, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->integer('current_quantity')->default(0);
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->integer('wholesale_min_qty')->nullable();
            $table->decimal('wholesale_price', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
