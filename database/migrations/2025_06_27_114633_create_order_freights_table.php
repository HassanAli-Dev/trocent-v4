<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_freights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            
            // === FREIGHT CLASSIFICATION ===
            $table->enum('freight_type', ['skid', 'box', 'envelope', 'weight'])->default('skid');
            $table->string('freight_description', 200)->nullable(); // FAK, Electronics, etc.
            
            // === PIECES & WEIGHT ===
            $table->integer('freight_pieces')->default(1); // Physical piece count
            $table->decimal('freight_weight', 10, 2); // Actual weight
            $table->enum('weight_unit', ['lbs', 'kg'])->default('lbs');
            $table->decimal('freight_chargeable_weight', 10, 2); // Billing weight (dimensional/actual)
            
            // === DIMENSIONS ===
            $table->decimal('freight_length', 8, 2)->default(0); // Length
            $table->decimal('freight_width', 8, 2)->default(0); // Width  
            $table->decimal('freight_height', 8, 2)->default(0); // Height
            $table->enum('dimension_unit', ['in', 'cm'])->default('in');
            
            // === SPECIAL PROPERTIES ===
            $table->boolean('is_stackable')->default(false); // Can stack with other freight
            $table->integer('stackable_value')->default(0); // Stackable multiplier (legacy)
            
            // === UNIT CONVERSION TRACKING ===
            $table->boolean('has_unit_conversion')->default(false); // Track if units were converted
            
            $table->timestamps();
            
            // === INDEXES ===
            $table->index(['order_id', 'freight_type']);
            $table->index('freight_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_freights');
    }
};