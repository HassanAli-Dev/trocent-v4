<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            // === BASIC ORDER INFO ===
            $table->string('order_code', 50)->unique(); // T-240101-001
            $table->string('quote_code', 50)->nullable(); // If converted from quote
            $table->boolean('is_quote')->default(false);
            $table->enum('service_type', ['regular', 'direct', 'rush'])->default('regular');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Created by
            
            // === ORDER STATUS ===
            $table->enum('status', [
                'pending',      // 1
                'quote',        // 2  
                'entered',      // 3
                'dispatched',   // 4
                'on_dock',      // 5
                'arrived_shipper', // 6
                'picked_up',    // 7
                'arrived_receiver', // 8
                'delivered',    // 9
                'approved',     // 10
                'billed',       // 11
                'cancelled'     // 12
            ])->default('entered');
            
            // === CUSTOMER INFO ===
            $table->string('reference_number')->nullable(); // Customer's reference
            $table->string('caller')->nullable(); // Who placed the order
            
            // === SHIPPER INFORMATION ===
            $table->string('shipper_name');
            $table->string('shipper_contact_name')->nullable();
            $table->string('shipper_email')->nullable();
            $table->string('shipper_phone')->nullable();
            $table->text('shipper_address');
            $table->string('shipper_suite')->nullable();
            $table->string('shipper_city');
            $table->string('shipper_province', 3); // ON, BC, etc
            $table->string('shipper_postal_code', 10);
            $table->text('shipper_special_instructions')->nullable();
            
            // === RECEIVER INFORMATION ===
            $table->string('receiver_name');
            $table->string('receiver_contact_name')->nullable();
            $table->string('receiver_email')->nullable();
            $table->string('receiver_phone')->nullable();
            $table->text('receiver_address');
            $table->string('receiver_suite')->nullable();
            $table->string('receiver_city');
            $table->string('receiver_province', 3);
            $table->string('receiver_postal_code', 10);
            $table->text('receiver_special_instructions')->nullable();
            
            // === CROSSDOCK INFORMATION ===
            $table->boolean('is_crossdock')->default(false);
            $table->string('crossdock_name')->nullable();
            $table->string('crossdock_contact_name')->nullable();
            $table->string('crossdock_email')->nullable();
            $table->string('crossdock_phone')->nullable();
            $table->text('crossdock_address')->nullable();
            $table->string('crossdock_suite')->nullable();
            $table->string('crossdock_city')->nullable();
            $table->string('crossdock_province', 3)->nullable();
            $table->string('crossdock_postal_code', 10)->nullable();
            $table->text('crossdock_special_instructions')->nullable();
            
            // === INTERLINE INFORMATION ===
            $table->boolean('interline_pickup')->default(false);
            $table->boolean('interline_delivery')->default(false);
            $table->foreignId('interline_id')->nullable()->constrained('delivery_agents')->nullOnDelete();
            $table->string('interline_name')->nullable();
            $table->string('interline_contact_name')->nullable();
            $table->string('interline_email')->nullable();
            $table->string('interline_phone')->nullable();
            $table->text('interline_address')->nullable();
            $table->string('interline_suite')->nullable();
            $table->string('interline_city')->nullable();
            $table->string('interline_province', 3)->nullable();
            $table->string('interline_postal_code', 10)->nullable();
            $table->text('interline_special_instructions')->nullable();
            
            // === PICKUP SCHEDULING ===
            $table->date('pickup_date');
            $table->time('pickup_time_from')->nullable();
            $table->time('pickup_time_to')->nullable();
            $table->boolean('pickup_appointment')->default(false);
            $table->text('pickup_appointment_number')->nullable();
            $table->text('pickup_dispatch_notes')->nullable();
            
            // === DELIVERY SCHEDULING ===
            $table->date('delivery_date');
            $table->time('delivery_time_from')->nullable();
            $table->time('delivery_time_to')->nullable();
            $table->boolean('delivery_appointment')->default(false);
            $table->text('delivery_appointment_number')->nullable();
            $table->text('delivery_dispatch_notes')->nullable();
            
            // === FREIGHT TOTALS (Calculated from order_freights) ===
            $table->integer('total_pieces')->default(0); // Actual physical pieces (user input)
            $table->integer('total_chargeable_pieces')->default(0); // Billing pieces (weight ÷ rule)
            $table->decimal('total_weight', 10, 2)->default(0); // Actual weight
            $table->decimal('total_chargeable_weight', 10, 2)->default(0); // Billing weight (dimensional/actual)
            $table->decimal('total_volume_weight', 10, 2)->nullable(); // Volume weight calculation
            $table->decimal('actual_weight', 10, 2)->nullable(); // Manual weight override
            
            // === MANUAL OVERRIDES ===
            $table->boolean('manual_freight')->default(false); // Skip auto-rate calculation
            $table->boolean('manual_skids')->default(false); // Manual piece count
            $table->boolean('manual_weight')->default(false); // Manual weight override
            $table->boolean('no_charges')->default(false); // Skip all charges
            
            // === FINANCIAL TOTALS ===
            $table->decimal('freight_rate', 10, 2)->default(0);
            $table->decimal('fuel_surcharge', 10, 2)->default(0);
            $table->decimal('accessorial_total', 10, 2)->default(0);
            $table->decimal('sub_total', 10, 2)->default(0);
            $table->decimal('provincial_tax', 10, 2)->default(0);
            $table->decimal('federal_tax', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            
            // === WAITING TIME CHARGES ===
            $table->decimal('waiting_time_charge', 10, 2)->default(0);
            $table->text('interline_charge_name')->nullable();
            $table->text('interline_charge_reference')->nullable();
            $table->decimal('interline_charge_amount', 10, 2)->nullable();
            
            // === TERMINAL/WAREHOUSE ===
            //$table->foreignId('terminal_id')->nullable()->constrained('warehouse_terminals')->nullOnDelete();
            
            // === AUDIT & NOTES ===
            $table->text('internal_notes')->nullable();
            $table->string('audit_number')->nullable();
            
            // === BILLING STATUS ===
            $table->boolean('is_invoiced')->default(false);
            $table->timestamp('invoiced_at')->nullable();
            //$table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            
            $table->softDeletes();
            $table->timestamps();
            
            // === INDEXES ===
            $table->index(['customer_id', 'status']);
            $table->index(['pickup_date', 'delivery_date']);
            $table->index(['shipper_city', 'receiver_city']);
            $table->index('is_crossdock');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};