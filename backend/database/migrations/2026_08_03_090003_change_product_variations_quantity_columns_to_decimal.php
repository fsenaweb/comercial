<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE product_variations ALTER COLUMN current_quantity TYPE numeric(12,3)');
        DB::statement('ALTER TABLE product_variations ALTER COLUMN min_quantity TYPE numeric(12,3)');
        DB::statement('ALTER TABLE product_variations ALTER COLUMN max_quantity TYPE numeric(12,3)');
        DB::statement('ALTER TABLE product_variations ALTER COLUMN wholesale_min_qty TYPE numeric(12,3)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE product_variations ALTER COLUMN current_quantity TYPE integer USING current_quantity::integer');
        DB::statement('ALTER TABLE product_variations ALTER COLUMN min_quantity TYPE integer USING min_quantity::integer');
        DB::statement('ALTER TABLE product_variations ALTER COLUMN max_quantity TYPE integer USING max_quantity::integer');
        DB::statement('ALTER TABLE product_variations ALTER COLUMN wholesale_min_qty TYPE integer USING wholesale_min_qty::integer');
    }
};
