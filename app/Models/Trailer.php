<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trailer extends Model
{
    protected $fillable = [
        'title',
        'leasing_company',
        'trailer_number',
        'plate_number',
        'reefer',
        'tailgate',
        'door_type',
    ];

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_trailer');
    }
}
