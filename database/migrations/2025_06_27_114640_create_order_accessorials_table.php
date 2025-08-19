<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_accessorials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            
            // === CHARGE CLASSIFICATION ===
            $table->enum('source', ['auto', 'manual'])->default('auto');
            // auto = calculated from accessorials table (type=1 in Electron)
            // manual = manually entered by user (type=2 in Electron)
            
            // === ACCESSORIAL REFERENCE (for auto charges) ===
            $table->foreignId('accessorial_id')->nullable()->constrained()->nullOnDelete();
            // Links to global accessorials table for auto charges
            // NULL for manual charges
            
            // === CHARGE DETAILS ===
            $table->string('name', 200); // Charge name/description
            $table->decimal('rate', 10, 2); // Base rate (from accessorial or manual)
            $table->integer('qty')->default(1); // Quantity/multiplier
            $table->decimal('amount', 10, 2); // Final calculated amount (rate * qty)
            
            // === CALCULATION METADATA ===
            $table->json('calculation_details')->nullable();
            // Stores calculation logic, triggers, customer overrides, etc.
            // Example: {"trigger": "crossdock", "customer_rate": 50.00, "base_rate": 30.00}
            
            // === FUEL SURCHARGE TRACKING ===
            $table->boolean('is_fuel_based')->default(false);
            // Some accessorials are subject to fuel surcharge, others aren't
            
            $table->timestamps();
            
            // === INDEXES ===
            $table->index(['order_id', 'source']);
            $table->index(['accessorial_id']); // For auto charges
            $table->index('source'); // Filter by auto vs manual
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_accessorials');
    }
};