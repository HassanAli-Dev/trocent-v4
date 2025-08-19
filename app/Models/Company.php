<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'legal_name',
        'operating_name',
        'contact_person',
        'phone',
        'email',
        'neq',
        'nir',
        'ifta',
        'auto_insurance_company',
        'auto_policy_number',
        'auto_policy_expiry',
        'auto_insurance_amount',
        'auto_insurance_company_2',
        'auto_policy_number_2',
        'auto_policy_expiry_2',
        'auto_insurance_amount_2',
        'cargo_insurance_company',
        'cargo_policy_number',
        'cargo_policy_expiry',
        'cargo_insurance_amount',
        'cargo_insurance_company_2',
        'cargo_policy_number_2',
        'cargo_policy_expiry_2',
        'cargo_insurance_amount_2',
    ];

    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'company_vehicle');
    }
    public function trailers()
    {
        return $this->belongsToMany(Trailer::class, 'company_trailer');
    }

    public function drivers()
    {
        return $this->belongsToMany(DeliveryAgent::class, 'company_driver')
            ->where('type', 'driver');
    }

    public function getTitleAttribute(): string
    {
        return $this->legal_name;
    }
}
