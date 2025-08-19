<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAgent extends Model
{
    protected $fillable = [
        'type',
        'driver_number',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'sin',
        'name',
        'contact_name',
        'phone',
        'email',
        'address',
        'suite',
        'city',
        'province',
        'postal_code',
        'license_number',
        'license_classes',
        'license_expiry',
        'tdg_certified',
        'tdg_expiry',
        'criminal_check_expiry',
        'criminal_check_note',
        'contract_type',
        'driver_description',
        'user_id',
    ];

    protected $attributes = [
        'name' => 'null',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_driver');
    }

    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'driver_vehicle');
    }

    public function trailers()
    {
        return $this->belongsToMany(Trailer::class, 'driver_trailer');
    }

    public function driverDocuments()
    {
        return $this->hasMany(DriverDocument::class);
    }
}
