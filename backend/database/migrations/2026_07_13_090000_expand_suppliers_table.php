<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['cnpj', 'contact']);

            $table->string('mobile_phone')->nullable()->after('trade_name');
            $table->string('phone')->nullable()->after('mobile_phone');
            $table->string('email')->nullable()->after('phone');
            $table->string('document')->nullable()->after('email');
            $table->boolean('is_company')->default(true)->after('document');
            // Genérico o suficiente pra servir Inscrição Estadual (PJ) ou RG (PF)
            // sem amarrar o schema a um dos dois — mesmo padrão do label dinâmico
            // já usado no formulário (ver Fornecedores.dc.html).
            $table->string('state_registration')->nullable()->after('is_company');
            $table->string('zip_code')->nullable()->after('address');
            $table->string('address_number')->nullable()->after('zip_code');
            $table->string('address_complement')->nullable()->after('address_number');
            $table->string('neighborhood')->nullable()->after('address_complement');
            $table->string('city')->nullable()->after('neighborhood');
            $table->string('state', 2)->nullable()->after('city');
            $table->text('notes')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'mobile_phone', 'phone', 'email', 'document', 'is_company', 'state_registration',
                'zip_code', 'address_number', 'address_complement', 'neighborhood', 'city', 'state', 'notes',
            ]);
            $table->string('cnpj')->nullable();
            $table->string('contact')->nullable();
        });
    }
};
