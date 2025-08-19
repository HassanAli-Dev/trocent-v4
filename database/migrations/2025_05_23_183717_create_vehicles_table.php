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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('serial_number')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->string('color')->nullable();
            $table->string('plate_number')->nullable();
            $table->date('plate_expiry')->nullable();

            $table->enum('tailgate', ['Yes', 'No'])->default('No');
            $table->enum('reefer', ['Yes', 'No'])->default('No');

            $table->decimal('max_weight', 8, 2)->nullable();
            $table->decimal('max_length', 8, 2)->nullable();
            $table->decimal('max_width', 8, 2)->nullable();
            $table->decimal('max_height', 8, 2)->nullable();
            $table->decimal('max_volume', 8, 2)->nullable();

            // File Uploads
            $table->string('truck_inspection_file')->nullable();
            $table->date('truck_inspection_date')->nullable();
            $table->string('registration_file')->nullable();
            $table->date('registration_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
