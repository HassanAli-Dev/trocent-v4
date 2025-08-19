<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'delivery_agent_id',
        'trip_date',
        'trip_type',
        'status',
        'driver_active',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'driver_active' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Trip belongs to a delivery agent (driver or interliner)
     */
    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    /**
     * Trip has many dispatch orders (legs)
     */
    public function dispatchOrders(): HasMany
    {
        return $this->hasMany(DispatchOrder::class);
    }

    /**
     * Trip has many driver activities
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DriverActivity::class);
    }

    // ==========================================
    // CONVENIENCE RELATIONSHIPS
    // ==========================================

    /**
     * Get the driver (alias for deliveryAgent when type is driver)
     */
    public function driver(): BelongsTo
    {
        return $this->deliveryAgent()->where('type', 'driver');
    }

    /**
     * Get the interliner (alias for deliveryAgent when type is interliner)
     */
    public function interliner(): BelongsTo
    {
        return $this->deliveryAgent()->where('type', 'interliner');
    }

    /**
     * Get pickup legs only
     */
    public function pickupLegs(): HasMany
    {
        return $this->dispatchOrders()->where('dispatch_type', 'P');
    }

    /**
     * Get delivery legs only
     */
    public function deliveryLegs(): HasMany
    {
        return $this->dispatchOrders()->whereIn('dispatch_type', ['D', 'PD']);
    }

    /**
     * Get completed legs
     */
    public function completedLegs(): HasMany
    {
        return $this->dispatchOrders()->where('status', 'completed');
    }

    /**
     * Get pending legs
     */
    public function pendingLegs(): HasMany
    {
        return $this->dispatchOrders()->where('status', 'pending');
    }

    // ==========================================
    // BUSINESS LOGIC METHODS
    // ==========================================

    /**
     * Check if trip is for a driver (vs interliner)
     */
    public function isDriverTrip(): bool
    {
        return $this->trip_type === 'driver';
    }

    /**
     * Check if trip is for an interliner
     */
    public function isInterlinerTrip(): bool
    {
        return $this->trip_type === 'interliner';
    }

    /**
     * Check if driver has started the trip
     */
    public function isDriverActive(): bool
    {
        return $this->driver_active;
    }

    /**
     * Check if trip is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if trip is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get total number of legs
     */
    public function getTotalLegs(): int
    {
        return $this->dispatchOrders()->count();
    }

    /**
     * Get number of completed legs
     */
    public function getCompletedLegsCount(): int
    {
        return $this->completedLegs()->count();
    }

    /**
     * Get trip progress percentage
     */
    public function getProgressPercentage(): int
    {
        $total = $this->getTotalLegs();
        if ($total === 0) return 0;
        
        $completed = $this->getCompletedLegsCount();
        return round(($completed / $total) * 100);
    }

    /**
     * Calculate total driver payout for this trip
     */
    public function calculateTotalDriverPayout(): float
    {
        return $this->dispatchOrders()->sum('driver_payout_amount') ?? 0;
    }

    /**
     * Calculate unpaid driver amount
     */
    public function calculateUnpaidAmount(): float
    {
        return $this->dispatchOrders()
            ->where('is_driver_paid', false)
            ->whereNotNull('driver_payout_amount')
            ->sum('driver_payout_amount') ?? 0;
    }

    /**
     * Check if all legs are paid
     */
    public function isFullyPaid(): bool
    {
        return $this->dispatchOrders()
            ->whereNotNull('driver_payout_amount')
            ->where('is_driver_paid', false)
            ->doesntExist();
    }

    /**
     * Get total pieces for this trip
     */
    public function getTotalPieces(): int
    {
        return $this->dispatchOrders()->sum('total_pieces') ?? 0;
    }

    /**
     * Get total weight for this trip
     */
    public function getTotalWeight(): float
    {
        return $this->dispatchOrders()->sum('total_weight') ?? 0;
    }

    /**
     * Get total distance for this trip
     */
    public function getTotalDistance(): float
    {
        return $this->dispatchOrders()->sum('distance_km') ?? 0;
    }

    /**
     * Get all unique cities in this trip
     */
    public function getCities(): array
    {
        $fromCities = $this->dispatchOrders()->pluck('from_city')->toArray();
        $toCities = $this->dispatchOrders()->pluck('to_city')->toArray();
        
        return array_unique(array_merge($fromCities, $toCities));
    }

    /**
     * Get trip route description
     */
    public function getRouteDescription(): string
    {
        $cities = $this->getCities();
        return implode(' → ', $cities);
    }

    /**
     * Start the trip (driver activation)
     */
    public function startTrip(): void
    {
        $this->update([
            'driver_active' => true,
            'status' => 'active'
        ]);
    }

    /**
     * Complete the trip
     */
    public function completeTrip(): void
    {
        $this->update([
            'status' => 'completed',
            'driver_active' => false
        ]);
    }

    /**
     * Check if trip can be started
     */
    public function canBeStarted(): bool
    {
        return $this->status === 'planning' && $this->getTotalLegs() > 0;
    }

    /**
     * Check if trip can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'active' && $this->getProgressPercentage() === 100;
    }

    /**
     * Get assigned vehicle
     */
    public function getAssignedVehicle(): ?Vehicle
    {
        return $this->deliveryAgent?->assignedVehicle;
    }

    /**
     * Get assigned trailer
     */
    public function getAssignedTrailer(): ?Trailer
    {
        return $this->deliveryAgent?->assignedTrailer;
    }

    /**
     * Get earliest scheduled time
     */
    public function getEarliestScheduledTime(): ?string
    {
        return $this->dispatchOrders()
            ->whereNotNull('scheduled_time_from')
            ->min('scheduled_time_from');
    }

    /**
     * Get latest scheduled time
     */
    public function getLatestScheduledTime(): ?string
    {
        return $this->dispatchOrders()
            ->whereNotNull('scheduled_time_to')
            ->max('scheduled_time_to');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope by trip type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('trip_type', $type);
    }

    /**
     * Scope driver trips
     */
    public function scopeDriverTrips($query)
    {
        return $query->where('trip_type', 'driver');
    }

    /**
     * Scope interliner trips
     */
    public function scopeInterlinerTrips($query)
    {
        return $query->where('trip_type', 'interliner');
    }

    /**
     * Scope by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope active trips
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope completed trips
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope by delivery agent
     */
    public function scopeByAgent($query, int $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('trip_date', [$startDate, $endDate]);
    }

    /**
     * Scope today's trips
     */
    public function scopeToday($query)
    {
        return $query->where('trip_date', today());
    }

    /**
     * Scope driver active trips
     */
    public function scopeDriverActive($query)
    {
        return $query->where('driver_active', true);
    }

    /**
     * Scope trips with unpaid legs
     */
    public function scopeWithUnpaidLegs($query)
    {
        return $query->whereHas('dispatchOrders', function($q) {
            $q->where('is_driver_paid', false)
              ->whereNotNull('driver_payout_amount')
              ->whereNotNull('completed_at');
        });
    }

    /**
     * Scope trips ready for pay (all legs completed)
     */
    public function scopeReadyForPay($query)
    {
        return $query->whereDoesntHave('dispatchOrders', function($q) {
            $q->where('status', '!=', 'completed');
        })->withUnpaidLegs();
    }
}