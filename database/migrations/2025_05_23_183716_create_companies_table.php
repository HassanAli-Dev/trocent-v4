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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('legal_name');
            $table->string('operating_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Company Numbers
            $table->string('neq')->nullable();
            $table->string('nir')->nullable();
            $table->string('ifta')->nullable();

            // Auto Insurance 1
            $table->string('auto_insurance_company')->nullable();
            $table->string('auto_policy_number')->nullable();
            $table->date('auto_policy_expiry')->nullable();
            $table->decimal('auto_insurance_amount', 10, 2)->nullable();

            // Auto Insurance 2
            $table->string('auto_insurance_company_2')->nullable();
            $table->string('auto_policy_number_2')->nullable();
            $table->date('auto_policy_expiry_2')->nullable();
            $table->decimal('auto_insurance_amount_2', 10, 2)->nullable();

            // Cargo Insurance 1
            $table->string('cargo_insurance_company')->nullable();
            $table->string('cargo_policy_number')->nullable();
            $table->date('cargo_policy_expiry')->nullable();
            $table->decimal('cargo_insurance_amount', 10, 2)->nullable();

            // Cargo Insurance 2
            $table->string('cargo_insurance_company_2')->nullable();
            $table->string('cargo_policy_number_2')->nullable();
            $table->date('cargo_policy_expiry_2')->nullable();
            $table->decimal('cargo_insurance_amount_2', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
