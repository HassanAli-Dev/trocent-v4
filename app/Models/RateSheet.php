<?php
// Add this to your existing app/Models/RateSheet.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateSheet extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'skid_by_weight',
        'province',
        'postal_code',
        'source_city',
        'destination_city',
        'rate_code',
        'external',
        'priority_sequence',
        'min_rate',
        'import_batch_id',
        'ltl'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function meta()
    {
        return $this->hasMany(RateSheetMeta::class);
    }


    protected static function booted()
    {
        // Clear cache when rate sheet is created, updated, or deleted
        static::created(function ($rateSheet) {
            $rateSheet->customer?->clearRateCache();
        });

        static::updated(function ($rateSheet) {
            $rateSheet->customer?->clearRateCache();
        });

        static::deleted(function ($rateSheet) {
            $rateSheet->customer?->clearRateCache();
        });
    }
}