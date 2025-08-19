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
        Schema::create('rate_sheet_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_sheet_id')->constrained('rate_sheets')->cascadeOnDelete();
            $table->string('name');   // e.g. "ltl", "500", "1000", or "3" (skid count)
            $table->string('value');  // can be parsed as float
            $table->timestamps();

            $table->index(['rate_sheet_id', 'name']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_sheet_meta');
    }
};
