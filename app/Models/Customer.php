<?php

namespace App\Models;

use App\Models\Accessorial;
use App\Models\User;
use App\Models\DeliveryAgent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'full_name',
        'account_number',
        'address',
        'suite',
        'city',
        'province',
        'postal_code',
        'account_contact',
        'account_status',
        'telephone_number',
        'fax_number',
        'billing_email',
        'pod_email',
        'status_update_email',
        'receive_status_update',
        'mandatory_reference_number',
        'summary_invoice',
        'terms_of_payment',
        'weight_to_pieces_rule',
        'account_opening_date',
        'last_invoice_date',
        'last_payment_date',
        'account_balance',
        'credit_limit',
        'fuel_surcharges',
        'fuel_surcharges_other_value',
        'fuel_surcharges_other',
        'fuel_surcharges_ftl',
        'fuel_surcharges_other_value_ftl',
        'fuel_surcharges_other_ftl',
        'language',
        'invoicing',
        'no_tax',
        'rush_service_charge',
        'rush_service_charge_min',
        'custom_logo',
        'fuel_surcharge_rule',
        'notification_preferences',
    ];

    protected $casts = [
        'account_status' => 'boolean',
        'receive_status_update' => 'boolean',
        'mandatory_reference_number' => 'boolean',
        'include_pod_with_invoice' => 'boolean',
        'summary_invoice' => 'boolean',
        'fuel_surcharges_other' => 'boolean',
        'fuel_surcharges_other_ftl' => 'boolean',
        'no_tax' => 'boolean',
        'billing_email' => 'array',
        'pod_email' => 'array',
        'status_update_email' => 'array',
        'notification_preferences' => 'array',
        'account_opening_date' => 'date',
        'last_invoice_date' => 'date',
        'last_payment_date' => 'date',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function accessorials()
    {
        return $this->belongsToMany(Accessorial::class, 'accessorial_customer')
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

    public function vehicleTypes()
    {
        return $this->belongsToMany(VehicleType::class, 'customer_vehicle_type')
            ->withPivot(['rate'])
            ->withTimestamps();
    }

    public function rateSheets(): HasMany
    {
        return $this->hasMany(RateSheet::class);
    }

    /**
     * FINAL: Build enhanced rate sheet cache with all improvements
     */
    public function buildEnhancedRateSheetCache(): array
    {
        $ttl = config('ratesheet.cache_ttl', 3600);
        $lockTimeout = config('ratesheet.cache_lock_timeout', 10);

        // Use cache lock to prevent concurrent rebuilds
        return Cache::lock("customer_rates_lock:{$this->id}", $lockTimeout)
            ->get(function () use ($ttl) {
                return Cache::remember("customer_rates:{$this->id}", $ttl, function() {
                    return $this->buildRateCacheFromDatabase();
                });
            });
    }

    /**
     * FINAL: Actual cache building logic with all improvements
     */
    private function buildRateCacheFromDatabase(): array
    {
        $startTime = microtime(true);

        $rateSheets = [
            'skid' => ['available_brackets' => []],
            'weight' => ['available_brackets' => []],
            'skid2' => ['available_brackets' => []]
        ];

        // Load all rate sheets with meta data for this customer
        $customerRates = RateSheet::with('meta')->where('customer_id', $this->id)
            ->orderBy('priority_sequence', 'desc')
            ->orderBy('destination_city', 'asc')
            ->get();

        foreach ($customerRates as $rateSheet) {
            $type = $rateSheet->type;
            $city = $this->normalizeCityName($rateSheet->destination_city);

            // Build available brackets per type
            foreach ($rateSheet->meta as $meta) {
                $bracket = trim($meta->name);
                if (!in_array($bracket, $rateSheets[$type]['available_brackets'])) {
                    $rateSheets[$type]['available_brackets'][] = $bracket;
                }
            }

            // If this is a skid_by_weight rate, also add to skid2 category
            if ($type === 'skid' && $rateSheet->skid_by_weight) {
                foreach ($rateSheet->meta as $meta) {
                    $bracket = trim($meta->name);
                    if (!in_array($bracket, $rateSheets['skid2']['available_brackets'])) {
                        $rateSheets['skid2']['available_brackets'][] = $bracket;
                    }
                }
            }

            // Pre-convert meta values to float
            $metaArray = [];
            if (!empty($rateSheet->ltl))
            {
                $metaArray[] = ["name" => "ltl", "value" => $rateSheet->ltl];
            }
            foreach ($rateSheet->meta as $meta) {
                $metaArray[] = [
                    'name' => trim($meta->name),
                    'value' => (float) $meta->value
                ];
            }

            // Group by city
            if (!isset($rateSheets[$type][$city])) {
                $rateSheets[$type][$city] = [];
            }

            $rateSheets[$type][$city][] = [
                'id' => $rateSheet->id, // For debugging
                'rate_code' => $rateSheet->rate_code ?? '',
                'external' => $rateSheet->external ?? 'I',
                'priority_sequence' => (int) $rateSheet->priority_sequence,
                'skid_by_weight' => (bool) $rateSheet->skid_by_weight,
                'min_rate' => (float) $rateSheet->min_rate,
                'meta' => $metaArray
            ];

            // Add to skid2 if needed
            if ($type === 'skid' && $rateSheet->skid_by_weight) {
                if (!isset($rateSheets['skid2'][$city])) {
                    $rateSheets['skid2'][$city] = [];
                }

                $rateSheets['skid2'][$city][] = [
                    'id' => $rateSheet->id,
                    'rate_code' => $rateSheet->rate_code ?? '',
                    'external' => $rateSheet->external ?? 'I',
                    'priority_sequence' => (int) $rateSheet->priority_sequence,
                    'skid_by_weight' => true,
                    'min_rate' => (float) $rateSheet->min_rate,
                    'meta' => $metaArray
                ];
            }
        }

        // Sort available brackets
        foreach ($rateSheets as $type => &$typeData) {
            if (!empty($typeData['available_brackets'])) {
                usort($typeData['available_brackets'], function($a, $b) {
                    if ($a === 'ltl') return -1;
                    if ($b === 'ltl') return 1;

                    if (is_numeric($a) && is_numeric($b)) {
                        return (int)$a - (int)$b;
                    }

                    return strcmp($a, $b);
                });
            }
        }

        $buildTime = microtime(true) - $startTime;

        // Log cache build if enabled
        if (config('ratesheet.logging.log_cache_builds', false)) {
            Log::info('Rate cache built for customer', [
                'customer_id' => $this->id,
                'build_time_ms' => round($buildTime * 1000, 2),
                'rate_sheet_count' => $customerRates->count(),
                'cache_size_kb' => round(strlen(serialize($rateSheets)) / 1024, 2)
            ]);
        }

        return $rateSheets;
    }

    /**
     * FINAL: Normalize city names like Electron did
     */
    private function normalizeCityName(string $cityName): string
    {
        return strtoupper(trim($cityName));
    }

    /**
     * FINAL: Clear cache when rate sheets change
     */
    public function clearRateCache(): void
    {
        Cache::forget("customer_rates:{$this->id}");

        if (config('ratesheet.logging.enabled', true)) {
            Log::info('Rate cache cleared for customer', ['customer_id' => $this->id]);
        }
    }

    /**
     * FINAL: Event listener to auto-clear cache when rates change
     */
    protected static function booted()
    {
        static::updated(function ($customer) {
            $customer->clearRateCache();
        });
    }
}
