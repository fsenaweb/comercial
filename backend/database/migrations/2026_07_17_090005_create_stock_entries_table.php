<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->string('nfe_number')->nullable();
            $table->string('nfe_series')->nullable();
            $table->string('nfe_key', 44)->nullable()->unique();
            $table->date('issue_date')->nullable();
            $table->decimal('freight_value', 10, 2)->default(0);
            $table->decimal('products_total', 10, 2);
            $table->decimal('total_value', 10, 2);
            $table->string('xml_path')->nullable();
            $table->unsignedBigInteger('generated_accounts_payable_id')->nullable();
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_entries');
    }
};
