<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('fuel_surcharges')->nullable()->change();
            $table->string('fuel_surcharges_ftl')->nullable()->change();
            $table->string('fuel_surcharges_other_value')->nullable()->change();
            $table->string('fuel_surcharges_other_value_ftl')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            //
        });
    }
};
