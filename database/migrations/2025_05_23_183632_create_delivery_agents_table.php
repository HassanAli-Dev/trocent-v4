<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            // New fields for drivers
            $table->string('driver_number')->nullable()->after('type');
            $table->string('first_name')->nullable()->after('driver_number');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('gender')->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('sin')->nullable()->after('date_of_birth');

            // Optional contact name
            $table->string('contact_name')->nullable()->after('name');

            // Address
            $table->string('address')->nullable()->after('email');
            $table->string('suite')->nullable()->after('address');
            $table->string('city')->nullable()->after('suite');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('province');

            // Driver metadata
            $table->string('license_number')->nullable()->after('postal_code');
            $table->string('license_classes')->nullable()->after('license_number');
            $table->date('license_expiry')->nullable()->after('license_classes');
            $table->boolean('tdg_certified')->default(false)->after('license_expiry');
            $table->date('tdg_expiry')->nullable()->after('tdg_certified');
            $table->date('criminal_check_expiry')->nullable()->after('tdg_expiry');
            $table->text('criminal_check_note')->nullable()->after('criminal_check_expiry');
            $table->string('contract_type')->nullable()->after('criminal_check_note');
            $table->string('driver_description')->nullable()->after('contract_type');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropColumn([
                'driver_number', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'sin',
                'contact_name', 'address', 'suite', 'city', 'province', 'postal_code',
                'license_number', 'license_classes', 'license_expiry',
                'tdg_certified', 'tdg_expiry', 'criminal_check_expiry', 'criminal_check_note',
                'contract_type', 'driver_description'
            ]);
        });
    }
};
