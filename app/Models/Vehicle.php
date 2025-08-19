<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'title',
        'serial_number',
        'make',
        'model',
        'year',
        'color',
        'plate_number',
        'plate_expiry',
        'tailgate',
        'reefer',
        'max_weight',
        'max_length',
        'max_width',
        'max_height',
        'max_volume',
        'truck_inspection_file',
        'truck_inspection_date',
        'registration_file',
        'registration_date',
    ];

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_vehicle');
    }
}
