<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Accessorial extends Model
{
    protected $fillable = [
        'name',
        'type',
        'driver_only',
        'amount',
        'free_time',
        'time_unit',
        'base_amount',
        'min',
        'max',
        'product_type',
        'amount_type',
    ];

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'accessorial_customer')
            ->withPivot([
                'amount',
                'free_time',
                'time_unit',
                'base_amount',
                'min',
                'max',
                'product_type',
                'amount_type',
            ])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::addGlobalScope('defaultOrder', function (Builder $builder) {
            $builder->orderBy('name')->orderBy('type');
        });

        static::saved(function (self $pivot) {
            Cache::forget("customer_accessorials:{$pivot->customer_id}");
        });

        static::deleted(function (self $pivot) {
            Cache::forget("customer_accessorials:{$pivot->customer_id}");
        });
    }
}
