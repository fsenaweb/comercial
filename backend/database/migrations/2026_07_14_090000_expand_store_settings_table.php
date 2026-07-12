<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('trade_name')->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->string('mobile_phone')->nullable()->after('phone');
            $table->string('zip_code')->nullable()->after('address');
            $table->string('address_number')->nullable()->after('zip_code');
            $table->string('address_complement')->nullable()->after('address_number');
            $table->string('neighborhood')->nullable()->after('address_complement');
            $table->string('city')->nullable()->after('neighborhood');
            $table->string('state', 2)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'trade_name',
                'email',
                'mobile_phone',
                'zip_code',
                'address_number',
                'address_complement',
                'neighborhood',
                'city',
                'state',
            ]);
        });
    }
};
