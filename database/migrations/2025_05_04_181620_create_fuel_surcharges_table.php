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
        Schema::create('fuel_surcharges', function (Blueprint $table) {
            $table->id();
            $table->decimal('ftl_surcharge', 5, 2)->nullable();
            $table->decimal('ltl_surcharge', 5, 2)->nullable();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_surcharges');
    }
};
