<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        Schema::table('accessorials', function (Blueprint $table) {
            DB::statement("ALTER TABLE accessorials MODIFY COLUMN type ENUM(
                'fixed_price',
                'time_based',
                'transport_based',
                'product_base',
                'fuel_based',
                'package_based'
            )");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
