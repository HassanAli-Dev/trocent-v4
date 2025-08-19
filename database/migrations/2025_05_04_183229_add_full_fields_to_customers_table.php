<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->string('suite')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('account_contact')->nullable();
            $table->boolean('account_status')->default(1);
            $table->string('telephone_number')->nullable();
            $table->string('fax_number')->nullable();
            $table->json('billing_email')->nullable();
            $table->json('pod_email')->nullable();
            $table->json('status_update_email')->nullable();
            $table->boolean('receive_status_update')->default(false);
            $table->boolean('mandatory_reference_number')->default(false);
            $table->boolean('summary_invoice')->default(false);
            $table->string('terms_of_payment')->nullable();
            $table->string('weight_to_pieces_rule')->nullable();
            $table->date('account_opening_date')->nullable();
            $table->date('last_invoice_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->decimal('account_balance', 10, 2)->nullable();
            $table->decimal('credit_limit', 10, 2)->nullable();
            $table->boolean('fuel_surcharges')->default(false);
            $table->decimal('fuel_surcharges_other_value', 8, 2)->nullable();
            $table->string('fuel_surcharges_other')->nullable();
            $table->boolean('fuel_surcharges_ftl')->default(false);
            $table->decimal('fuel_surcharges_other_value_ftl', 8, 2)->nullable();
            $table->string('fuel_surcharges_other_ftl')->nullable();
            $table->string('language')->nullable();
            $table->string('invoicing')->nullable();
            $table->boolean('no_tax')->default(false);
            $table->boolean('include_pod_with_invoice')->default(false);
            $table->decimal('rush_service_charge', 8, 2)->nullable();
            $table->decimal('rush_service_charge_min', 8, 2)->nullable();
            $table->string('custom_logo')->nullable();
            $table->string('fuel_surcharge_rule')->nullable();
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'suite',
                'city',
                'province',
                'postal_code',
                'account_contact',
                'account_status',
                'telephone_number',
                'fax_number',
                'billing_email',
                'pod_email',
                'status_update_email',
                'receive_status_update',
                'mandatory_reference_number',
                'summary_invoice',
                'terms_of_payment',
                'weight_to_pieces_rule',
                'account_opening_date',
                'last_invoice_date',
                'last_payment_date',
                'account_balance',
                'credit_limit',
                'fuel_surcharges',
                'fuel_surcharges_other_value',
                'fuel_surcharges_other',
                'fuel_surcharges_ftl',
                'fuel_surcharges_other_value_ftl',
                'fuel_surcharges_other_ftl',
                'language',
                'invoicing',
                'no_tax',
                'rush_service_charge',
                'rush_service_charge_min',
                'custom_logo',
                'fuel_surcharge_rule',
                'notification_preferences',
            ]);
        });
    }
};
