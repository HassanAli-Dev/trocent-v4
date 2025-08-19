<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class VehicleType extends Model
{
    protected $fillable = ['name', 'rate'];

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_vehicle_type')
            ->withPivot(['rate'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saved(function (self $pivot) {
            Cache::forget("customer_vehicle_types:{$pivot->customer_id}");
        });

        static::deleted(function (self $pivot) {
            Cache::forget("customer_vehicle_types:{$pivot->customer_id}");
        });
    }
}
