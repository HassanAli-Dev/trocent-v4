<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            
            // === LEG IDENTIFICATION ===
            $table->enum('dispatch_type', ['P', 'D', 'PD']); // Pickup, Delivery, or Combined
            $table->integer('leg_sequence')->default(1); // For cross-dock: 1=pickup leg, 2=delivery leg
            $table->foreignId('trip_id')->nullable();
            
            // === FROM ADDRESS (Pickup Location) ===
            $table->string('from_name');
            $table->string('from_contact_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_phone')->nullable();
            $table->text('from_address');
            $table->string('from_suite')->nullable();
            $table->string('from_city');
            $table->string('from_province', 3);
            $table->string('from_postal_code', 10);
            $table->text('from_special_instructions')->nullable();
            
            // === TO ADDRESS (Delivery Location) ===
            $table->string('to_name');
            $table->string('to_contact_name')->nullable();
            $table->string('to_email')->nullable();
            $table->string('to_phone')->nullable();
            $table->text('to_address');
            $table->string('to_suite')->nullable();
            $table->string('to_city');
            $table->string('to_province', 3);
            $table->string('to_postal_code', 10);
            $table->text('to_special_instructions')->nullable();
            
            // === SCHEDULED TIMES ===
            $table->date('scheduled_date');
            $table->time('scheduled_time_from')->nullable();
            $table->time('scheduled_time_to')->nullable();
            $table->boolean('requires_appointment')->default(false);
            $table->string('appointment_number')->nullable();
            $table->text('dispatch_notes')->nullable();
            
            // === ACTUAL EXECUTION TIMES ===
            $table->timestamp('arrived_at')->nullable(); // When driver arrived
            $table->timestamp('started_at')->nullable(); // When loading/unloading started
            $table->timestamp('completed_at')->nullable(); // When leg was completed
            $table->timestamp('departed_at')->nullable(); // When driver left location
            
            // === OPERATIONAL STATUS ===
            $table->enum('status', [
                'pending',           // Not yet dispatched
                'dispatched',        // Assigned to driver
                'en_route',         // Driver en route to location
                'arrived',          // Driver arrived at location
                'in_progress',      // Loading/unloading in progress
                'completed',        // Leg completed successfully
                'exception'         // Issue occurred
            ])->default('pending');
            
            // === WAITING TIME TRACKING ===
            $table->integer('waiting_minutes')->default(0); // Total waiting time
            $table->timestamp('waiting_started_at')->nullable();
            $table->timestamp('waiting_ended_at')->nullable();
            
            // === SIGNATURES & PROOF OF DELIVERY ===
            $table->string('signature_path')->nullable(); // Path to signature image
            $table->string('signee_name')->nullable(); // Who signed
            $table->json('photo_paths')->nullable(); // Array of photo paths
            
            // === DRIVER PAYMENT (Key Enhancement!) ===
            $table->decimal('driver_payout_amount', 10, 2)->nullable(); // Payment for this leg
            $table->boolean('is_driver_paid')->default(false); // Payment status
            $table->timestamp('payout_approved_at')->nullable(); // When payment approved
            $table->foreignId('payout_approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // === PERFORMANCE METRICS ===
            $table->integer('total_pieces')->default(0); // Pieces handled in this leg
            $table->decimal('total_weight', 10, 2)->default(0); // Weight handled in this leg
            $table->decimal('distance_km', 8, 2)->nullable(); // Distance traveled for this leg
            
            // === AUDIT & REVISION ===
            $table->integer('revision_count')->default(0); // Track order changes
            $table->string('audit_number')->nullable();
            
            $table->timestamps();
            
            // === INDEXES ===
            $table->index(['order_id', 'leg_sequence']); // Leg ordering
            $table->index(['trip_id', 'dispatch_type']); // Trip planning
            $table->index(['status', 'scheduled_date']); // Dispatch board
            $table->index(['is_driver_paid', 'completed_at']); // Driver pay queries
            $table->index(['from_city', 'to_city']); // Route optimization
            
            // === CONSTRAINTS ===
            $table->unique(['order_id', 'dispatch_type', 'leg_sequence']); // No duplicate legs
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_orders');
    }
};