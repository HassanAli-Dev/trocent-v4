<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            
            // === RESOURCE ASSIGNMENT ===
            $table->foreignId('delivery_agent_id')->constrained()->cascadeOnDelete();
            // Note: vehicle/trailer assignment handled via delivery_agent relationship
            
            // === TRIP BASIC INFO ===
            $table->date('trip_date'); // When trip is scheduled
            $table->enum('trip_type', ['driver', 'interliner'])->default('driver');
            // driver = internal driver (trip_type=1 in Electron)
            // interliner = external carrier (trip_type=2 in Electron)
            
            // === TRIP STATUS ===
            $table->enum('status', [
                'planning',      // Being built (trip_status=0 in Electron)
                'active',        // Ready/in progress (trip_status=1 in Electron)  
                'completed'      // All legs done (trip_status=2 in Electron)
            ])->default('planning');
            
            // === DRIVER CONTROL ===
            $table->boolean('driver_active')->default(false); // Driver started trip (from Electron)
            
            $table->timestamps();
            $table->softDeletes(); // Preserve for audit trail (Electron uses soft deletes)
            
            // === INDEXES ===
            $table->index(['delivery_agent_id', 'trip_date']); // Driver schedules
            $table->index(['status', 'trip_date']); // Dispatch board filtering
            $table->index('trip_type'); // Driver vs interliner filtering
            $table->index('driver_active'); // Real-time tracking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};