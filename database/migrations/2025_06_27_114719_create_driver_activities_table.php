<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_activities', function (Blueprint $table) {
            $table->id();
            
            // === BASIC REFERENCES ===
            $table->foreignId('delivery_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dispatch_order_id')->nullable()->constrained()->nullOnDelete();
            
            // === ACTIVITY DETAILS ===
            $table->enum('activity_type', [
                // Trip-level activities
                'trip_started',
                'trip_paused', 
                'trip_resumed',
                'trip_completed',
                
                // Location-based activities
                'arrived_pickup',
                'departed_pickup', 
                'arrived_delivery',
                'departed_delivery',
                'en_route',
                
                // Status updates
                'break_started',
                'break_ended',
                'issue_reported',
                'delivery_attempted',
                'signature_captured',
                
                // System events
                'app_opened',
                'app_closed',
                'location_update'
            ]);
            
            $table->timestamp('activity_timestamp'); // When activity occurred
            $table->text('notes')->nullable(); // Driver notes/details
            
            // === LOCATION TRACKING ===
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable(); // Reverse geocoded address
            $table->integer('accuracy_meters')->nullable(); // GPS accuracy
            
            // === METADATA ===
            $table->json('metadata')->nullable();
            // Examples:
            // {"odometer": 125543, "fuel_level": 75}
            // {"signature_path": "/signatures/123.png"}
            // {"issue_type": "traffic_delay", "duration_minutes": 30}
            // {"photo_paths": ["/photos/pod1.jpg", "/photos/pod2.jpg"]}
            
            // === SYSTEM INFO ===
            $table->string('app_version')->nullable(); // Mobile app version
            $table->string('device_info')->nullable(); // Device details
            $table->boolean('is_offline_sync')->default(false); // Synced from offline storage
            
            $table->timestamps();
            
            // === INDEXES ===
            $table->index(['delivery_agent_id', 'activity_timestamp']); // Driver timeline
            $table->index(['trip_id', 'activity_timestamp']); // Trip tracking
            $table->index(['dispatch_order_id', 'activity_type']); // Leg-specific activities
            $table->index(['activity_type', 'activity_timestamp']); // Activity reporting
            $table->index(['latitude', 'longitude']); // Geospatial queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_activities');
    }
};