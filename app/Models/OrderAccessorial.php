<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAccessorial extends Model
{
    use HasFactory;

    protected $table = 'order_accessorials';

    protected $fillable = [
        'order_id',
        'source',
        'accessorial_id',
        'name',
        'rate',
        'qty',
        'amount',
        'calculation_details',
        'is_fuel_based',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'qty' => 'integer',
        'amount' => 'decimal:2',
        'calculation_details' => 'array',
        'is_fuel_based' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Accessorial belongs to an order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Accessorial may reference a global accessorial (for auto charges)
     */
    public function accessorial(): BelongsTo
    {
        return $this->belongsTo(Accessorial::class);
    }

    // ==========================================
    // BUSINESS LOGIC METHODS
    // ==========================================

    /**
     * Check if this is an automatically calculated charge
     */
    public function isAutoCalculated(): bool
    {
        return $this->source === 'auto' && !is_null($this->accessorial_id);
    }

    /**
     * Check if this is a manually entered charge
     */
    public function isManualCharge(): bool
    {
        return $this->source === 'manual';
    }

    /**
     * Calculate amount based on rate and quantity
     */
    public function calculateAmount(): float
    {
        return $this->rate * $this->qty;
    }

    /**
     * Get the trigger that caused this accessorial (for auto charges)
     */
    public function getTrigger(): ?string
    {
        return $this->calculation_details['trigger'] ?? null;
    }

    /**
     * Get customer override rate (if applicable)
     */
    public function getCustomerOverride(): ?float
    {
        return $this->calculation_details['customer_override'] ?? null;
    }

    /**
     * Get base rate from accessorial table
     */
    public function getBaseRate(): ?float
    {
        return $this->calculation_details['base_rate'] ?? $this->rate;
    }

    /**
     * Get applied rate (could be base rate or customer override)
     */
    public function getAppliedRate(): float
    {
        return $this->calculation_details['applied_rate'] ?? $this->rate;
    }

    /**
     * Get calculation reason/notes
     */
    public function getCalculationReason(): ?string
    {
        return $this->calculation_details['reason'] ?? null;
    }

    /**
     * Check if charge is subject to fuel surcharge
     */
    public function isSubjectToFuelSurcharge(): bool
    {
        return $this->is_fuel_based;
    }

    /**
     * Calculate fuel surcharge portion of this accessorial
     */
    public function calculateFuelSurchargePortion(float $fuelSurchargePercentage): float
    {
        if (!$this->isSubjectToFuelSurcharge()) {
            return 0;
        }
        
        return $this->amount * ($fuelSurchargePercentage / 100);
    }

    /**
     * Get display name for the charge
     */
    public function getDisplayName(): string
    {
        return $this->name ?: ($this->accessorial->name ?? 'Unknown Charge');
    }

    /**
     * Get charge description with details
     */
    public function getDescription(): string
    {
        $description = $this->getDisplayName();
        
        if ($this->qty > 1) {
            $description .= " (×{$this->qty})";
        }
        
        if ($trigger = $this->getTrigger()) {
            $description .= " - {$trigger}";
        }
        
        return $description;
    }

    /**
     * Check if charge was manually overridden
     */
    public function wasOverridden(): bool
    {
        $customerOverride = $this->getCustomerOverride();
        $baseRate = $this->getBaseRate();
        
        return $customerOverride && $customerOverride !== $baseRate;
    }

    /**
     * Get override information for display
     */
    public function getOverrideInfo(): ?array
    {
        if (!$this->wasOverridden()) {
            return null;
        }
        
        return [
            'base_rate' => $this->getBaseRate(),
            'override_rate' => $this->getCustomerOverride(),
            'reason' => $this->getCalculationReason()
        ];
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope to get auto-calculated charges
     */
    public function scopeAutoCalculated($query)
    {
        return $query->where('source', 'auto');
    }

    /**
     * Scope to get manual charges
     */
    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }

    /**
     * Scope to get fuel-based charges
     */
    public function scopeFuelBased($query)
    {
        return $query->where('is_fuel_based', true);
    }

    /**
     * Scope to get charges by accessorial type
     */
    public function scopeByAccessorial($query, int $accessorialId)
    {
        return $query->where('accessorial_id', $accessorialId);
    }

    /**
     * Scope to get charges triggered by specific event
     */
    public function scopeByTrigger($query, string $trigger)
    {
        return $query->whereJsonContains('calculation_details->trigger', $trigger);
    }

    // ==========================================
    // STATIC METHODS FOR CHARGE CREATION
    // ==========================================

    /**
     * Create an auto-calculated accessorial charge
     */
    public static function createAutoCharge(
        int $orderId,
        int $accessorialId,
        float $rate,
        int $qty = 1,
        array $calculationDetails = []
    ): self {
        $accessorial = Accessorial::find($accessorialId);
        
        return self::create([
            'order_id' => $orderId,
            'source' => 'auto',
            'accessorial_id' => $accessorialId,
            'name' => $accessorial->name,
            'rate' => $rate,
            'qty' => $qty,
            'amount' => $rate * $qty,
            'calculation_details' => $calculationDetails,
            'is_fuel_based' => $accessorial->is_fuel_based ?? false,
        ]);
    }

    /**
     * Create a manual accessorial charge
     */
    public static function createManualCharge(
        int $orderId,
        string $name,
        float $rate,
        int $qty = 1,
        ?string $reason = null
    ): self {
        return self::create([
            'order_id' => $orderId,
            'source' => 'manual',
            'accessorial_id' => null,
            'name' => $name,
            'rate' => $rate,
            'qty' => $qty,
            'amount' => $rate * $qty,
            'calculation_details' => $reason ? ['reason' => $reason] : [],
            'is_fuel_based' => false,
        ]);
    }
}