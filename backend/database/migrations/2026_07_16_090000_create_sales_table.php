<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('cash_register_id')->nullable()->constrained();
            $table->decimal('subtotal', 10, 2);
            $table->string('discount_type')->default('fixed');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->foreignId('payment_method_id')->nullable()->constrained();
            $table->text('notes')->nullable();
            $table->string('status')->default('completed');
            $table->string('canceled_reason')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users');
            $table->date('expires_at')->nullable();
            $table->foreignId('converted_to_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
