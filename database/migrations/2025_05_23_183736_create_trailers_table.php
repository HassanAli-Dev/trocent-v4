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
        Schema::create('trailers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('leasing_company')->nullable();
            $table->string('trailer_number')->nullable();
            $table->string('plate_number')->nullable();
            $table->enum('reefer', ['Yes', 'No'])->default('No');
            $table->enum('tailgate', ['Yes', 'No'])->default('No');
            $table->enum('door_type', ['Barn door', 'Rollup door'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trailers');
    }
};
