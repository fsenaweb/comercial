<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE account_entry_items ALTER COLUMN quantity TYPE numeric(12,3)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE account_entry_items ALTER COLUMN quantity TYPE integer USING quantity::integer');
    }
};
