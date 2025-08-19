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
        Schema::create('accessorials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['fixed_price', 'time_based', 'transport_based', 'product_based']);
            $table->boolean('driver_only')->default(false);

            // Default global pricing fields
            $table->decimal('amount', 8, 2)->nullable();
            $table->integer('free_time')->nullable();
            $table->string('time_unit')->nullable();
            $table->decimal('base_amount', 8, 2)->nullable();
            $table->decimal('min', 8, 2)->nullable();
            $table->decimal('max', 8, 2)->nullable();
            $table->string('product_type')->nullable();
            $table->string('amount_type')->nullable(); // 'fixed', 'percentage'

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessorials');
    }
};
