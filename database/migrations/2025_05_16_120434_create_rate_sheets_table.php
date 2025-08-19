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
        Schema::create('rate_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['skid', 'weight']);
            $table->boolean('skid_by_weight')->default(false);
            $table->string('destination_city');
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('rate_code')->nullable();
            $table->enum('external', ['I', 'E'])->default('I');
            $table->integer('priority_sequence')->default(0);
            $table->decimal('min_rate', 10, 2)->nullable();
            $table->decimal('ltl', 10, 2)->nullable();
            $table->timestamps();
            $table->string('import_batch_id')->nullable()->index();

            $table->index(
                ['customer_id', 'destination_city', 'rate_code', 'type'],
                'rate_sheets_multi_index'
            );
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_sheets');
    }
};
