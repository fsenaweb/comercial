<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('corporate_name');
            $table->string('trade_name')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('document')->nullable();
            $table->boolean('is_company')->default(true);
            // Genérico o suficiente pra servir Inscrição Estadual (PJ) ou RG (PF)
            // sem amarrar o schema a um dos dois — mesmo padrão do label dinâmico
            // já usado no formulário (ver Fornecedores.dc.html).
            $table->string('state_registration')->nullable();
            $table->string('address')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('address_number')->nullable();
            $table->string('address_complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
