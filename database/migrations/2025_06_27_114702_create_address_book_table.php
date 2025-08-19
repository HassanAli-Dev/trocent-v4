<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_book', function (Blueprint $table) {
            $table->id();
            
            // === COMPANY/LOCATION INFO ===
            $table->string('name'); // Company/location name (stored UPPERCASE)
            $table->string('contact_name')->nullable(); // Contact person (stored UPPERCASE)
            
            // === CONTACT DETAILS ===
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            // === ADDRESS INFO ===
            $table->text('address'); // Street address (stored UPPERCASE)
            $table->string('suite')->nullable(); // Unit/suite (stored UPPERCASE)
            $table->string('city'); // City (stored UPPERCASE)
            $table->string('province', 3); // Province code: ON, BC, etc (stored UPPERCASE)
            $table->string('postal_code', 10); // Postal code (stored UPPERCASE)
            
            // === SPECIAL INSTRUCTIONS ===
            $table->text('special_instructions')->nullable(); // Delivery notes (stored UPPERCASE)
            
            // === OPERATING HOURS ===
            $table->time('operating_hours_from')->nullable(); // Opening time
            $table->time('operating_hours_to')->nullable(); // Closing time
            
            // === OPERATIONAL FLAGS ===
            $table->boolean('requires_appointment')->default(false); // Appointment required
            $table->boolean('no_waiting_time')->default(false); // No waiting time charges
            
            // === USAGE TRACKING ===
            $table->integer('usage_count')->default(0); // Track how often used
            $table->timestamp('last_used_at')->nullable(); // Last time selected
            
            $table->timestamps();
            
            // === INDEXES ===
            $table->index('name'); // Primary search field
            $table->index(['city', 'province']); // Location-based search
            $table->index('postal_code'); // Postal code search
            $table->index('usage_count'); // Popular addresses first
            
            // === SEARCH OPTIMIZATION ===
            $table->index(['name', 'city']); // Combined name + city search
            $table->fullText(['name', 'address', 'city']); // Full-text search capability
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address_book');
    }
};