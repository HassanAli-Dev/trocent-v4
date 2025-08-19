<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class AddressBook extends Model
{
    use HasFactory;

    protected $table = 'address_book';

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'address',
        'suite',
        'city',
        'province',
        'postal_code',
        'special_instructions',
        'operating_hours_from',
        'operating_hours_to',
        'requires_appointment',
        'no_waiting_time',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'requires_appointment' => 'boolean',
        'no_waiting_time' => 'boolean',
        'operating_hours_from' => 'datetime:H:i',
        'operating_hours_to' => 'datetime:H:i',
        'last_used_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    // Auto-uppercase specific fields on save
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Convert text fields to uppercase (preserving Electron behavior)
            $uppercaseFields = [
                'name',
                'contact_name', 
                'address',
                'suite',
                'city',
                'province',
                'postal_code',
                'special_instructions'
            ];

            foreach ($uppercaseFields as $field) {
                if (!is_null($model->$field)) {
                    $model->$field = strtoupper($model->$field);
                }
            }
        });
    }

    // Scopes for common queries
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function ($query, $search) {
            return $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%")
                      ->orWhere('postal_code', 'like', "%{$search}%");
            });
        });
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderBy('usage_count', 'desc');
    }

    public function scopeRecentlyUsed(Builder $query): Builder
    {
        return $query->whereNotNull('last_used_at')
                     ->orderBy('last_used_at', 'desc');
    }

    // Business methods
    public function markAsUsed(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->suite ? "Unit {$this->suite}" : null,
            $this->city,
            $this->province,
            $this->postal_code
        ]);

        return implode(', ', $parts);
    }

    public function getOperatingHoursAttribute(): ?string
    {
        if (!$this->operating_hours_from || !$this->operating_hours_to) {
            return null;
        }

        return sprintf(
            '%s - %s',
            $this->operating_hours_from->format('H:i'),
            $this->operating_hours_to->format('H:i')
        );
    }

    public function isOpenAt(Carbon $time): bool
    {
        if (!$this->operating_hours_from || !$this->operating_hours_to) {
            return true; // Assume open if no hours specified
        }

        $checkTime = $time->format('H:i');
        $fromTime = $this->operating_hours_from->format('H:i');
        $toTime = $this->operating_hours_to->format('H:i');

        return $checkTime >= $fromTime && $checkTime <= $toTime;
    }

    // Prevent duplicate entries (preserving Electron logic)
    public static function findOrCreateAddress(array $attributes): self
    {
        // Normalize data
        $searchData = [
            'address' => strtoupper($attributes['address']),
            'city' => strtoupper($attributes['city']),
            'province' => strtoupper($attributes['province']),
            'postal_code' => strtoupper($attributes['postal_code']),
        ];

        // Handle suite matching (null or empty = same)
        $suite = isset($attributes['suite']) ? strtoupper($attributes['suite']) : null;
        
        $query = static::where($searchData)
            ->where(function ($query) use ($suite) {
                if (empty($suite)) {
                    $query->whereNull('suite')->orWhere('suite', '');
                } else {
                    $query->where('suite', $suite);
                }
            });

        $existing = $query->first();

        if ($existing) {
            // Update existing record with new data
            $existing->update([
                'name' => strtoupper($attributes['name']),
                'contact_name' => isset($attributes['contact_name']) ? strtoupper($attributes['contact_name']) : $existing->contact_name,
                'phone' => $attributes['phone'] ?? $existing->phone,
                'email' => $attributes['email'] ?? $existing->email,
            ]);
            
            return $existing;
        }

        // Create new record
        return static::create($attributes);
    }
}