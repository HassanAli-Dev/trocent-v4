<?php
// Add this to your existing app/Models/RateSheetMeta.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateSheetMeta extends Model
{
    protected $table = 'rate_sheet_meta';
    protected $fillable = [
        'rate_sheet_id',
        'name',
        'value',
    ];

    public function rateSheet()
    {
        return $this->belongsTo(RateSheet::class);
    }

   
    protected static function booted()
    {
        // Clear cache when rate sheet meta is created, updated, or deleted
        static::created(function ($meta) {
            $meta->rateSheet?->customer?->clearRateCache();
        });

        static::updated(function ($meta) {
            $meta->rateSheet?->customer?->clearRateCache();
        });

        static::deleted(function ($meta) {
            $meta->rateSheet?->customer?->clearRateCache();
        });
    }
}