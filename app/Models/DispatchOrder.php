<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchOrder extends Model
{
    use HasFactory;

    protected $table = 'dispatch_orders';

    protected $fillable = [
        'order_id',
        'dispatch_type',
        'leg_sequence',
        'trip_id',
        
        // From address
        'from_name',
        'from_contact_name',
        'from_email',
        'from_phone',
        'from_address',
        'from_suite',
        'from_city',
        'from_province',
        'from_postal_code',
        'from_special_instructions',
        
        // To address
        'to_name',
        'to_contact_name',
        'to_email',
        'to_phone',
        'to_address',
        'to_suite',
        'to_city',
        'to_province',
        'to_postal_code',
        'to_special_instructions',
        
        // Scheduling
        'scheduled_date',
        'scheduled_time_from',
        'scheduled_time_to',
        'requires_appointment',
        'appointment_number',
        'dispatch_notes',
        
        // Execution times
        'arrived_at',
        'started_at',
        'completed_at',
        'departed_at',
        
        // Status
        'status',
        
        // Waiting time
        'waiting_minutes',
        'waiting_started_at',
        'waiting_ended_at',
        
        // Proof of delivery
        'signature_path',
        'signee_name',
        'photo_paths',
        
        // Driver payment
        'driver_payout_amount',
        'is_driver_paid',
        'payout_approved_at',
        'payout_approved_by',
        
        // Performance
        'total_pieces',
        'total_weight',
        'distance_km',
        
        // Audit
        'revision_count',
        'audit_number',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time_from' => 'datetime:H:i',
        'scheduled_time_to' => 'datetime:H:i',
        'requires_appointment' => 'boolean',
        'arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'departed_at' => 'datetime',
        'waiting_minutes' => 'integer',
        'waiting_started_at' => 'datetime',
        'waiting_ended_at' => 'datetime',
        'photo_paths' => 'array',
        'driver_payout_amount' => 'decimal:2',
        'is_driver_paid' => 'boolean',
        'payout_approved_at' => 'datetime',
        'total_pieces' => 'integer',
        'total_weight' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'revision_count' => 'integer',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Dispatch order belongs to an order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Dispatch order belongs to a trip
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * User who approved the payout
     */
    public function payoutApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payout_approved_by');
    }

    /**
     * Driver activities for this dispatch order
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DriverActivity::class);
    }

    // ==========================================
    // BUSINESS LOGIC METHODS
    // ==========================================

    /**
     * Check if this is a pickup leg
     */
    public function isPickupLeg(): bool
    {
        return $this->dispatch_type === 'P';
    }

    /**
     * Check if this is a delivery leg
     */
    public function isDeliveryLeg(): bool
    {
        return in_array($this->dispatch_type, ['D', 'PD']);
    }

    /**
     * Check if this is a combined pickup/delivery leg
     */
    public function isCombinedLeg(): bool
    {
        return $this->dispatch_type === 'PD';
    }

    /**
     * Get the driver from the trip
     */
    public function getDriver(): ?DeliveryAgent
    {
        return $this->trip?->deliveryAgent;
    }

    /**
     * Get vehicle from the driver
     */
    public function getVehicle(): ?Vehicle
    {
        return $this->getDriver()?->assignedVehicle;
    }

    /**
     * Check if leg is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if leg is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['dispatched', 'en_route', 'arrived', 'in_progress']);
    }

    /**
     * Check if driver has started this leg
     */
    public function isStarted(): bool
    {
        return !is_null($this->started_at);
    }

    /**
     * Calculate waiting time in minutes
     */
    public function calculateWaitingTime(): int
    {
        if (!$this->waiting_started_at || !$this->waiting_ended_at) {
            return $this->waiting_minutes ?? 0;
        }
        
        return $this->waiting_started_at->diffInMinutes($this->waiting_ended_at);
    }

    /**
     * Calculate billable waiting time (after free time)
     */
    public function calculateBillableWaitingTime(int $freeTimeMinutes = 30): int
    {
        $totalWaiting = $this->calculateWaitingTime();
        return max(0, $totalWaiting - $freeTimeMinutes);
    }

    /**
     * Get execution duration in minutes
     */
    public function getExecutionDuration(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        
        return $this->started_at->diffInMinutes($this->completed_at);
    }

    /**
     * Get total time on site (arrived to departed)
     */
    public function getTotalTimeOnSite(): ?int
    {
        if (!$this->arrived_at || !$this->departed_at) {
            return null;
        }
        
        return $this->arrived_at->diffInMinutes($this->departed_at);
    }

    /**
     * Check if leg is running late
     */
    public function isRunningLate(): bool
    {
        if (!$this->scheduled_time_to) {
            return false;
        }
        
        $scheduledDateTime = $this->scheduled_date->setTimeFromTimeString($this->scheduled_time_to);
        
        if ($this->isCompleted()) {
            return $this->completed_at > $scheduledDateTime;
        }
        
        return now() > $scheduledDateTime && !$this->isCompleted();
    }

    /**
     * Get delay in minutes (if late)
     */
    public function getDelayMinutes(): int
    {
        if (!$this->isRunningLate()) {
            return 0;
        }
        
        $scheduledDateTime = $this->scheduled_date->setTimeFromTimeString($this->scheduled_time_to);
        $actualDateTime = $this->completed_at ?? now();
        
        return $scheduledDateTime->diffInMinutes($actualDateTime);
    }

    /**
     * Get formatted address string
     */
    public function getFromAddressString(): string
    {
        $parts = array_filter([
            $this->from_address,
            $this->from_suite,
            $this->from_city,
            $this->from_province,
            $this->from_postal_code
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Get formatted to address string
     */
    public function getToAddressString(): string
    {
        $parts = array_filter([
            $this->to_address,
            $this->to_suite,
            $this->to_city,
            $this->to_province,
            $this->to_postal_code
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Get route description
     */
    public function getRouteDescription(): string
    {
        return "{$this->from_city} → {$this->to_city}";
    }

    /**
     * Check if payout is approved
     */
    public function isPayoutApproved(): bool
    {
        return !is_null($this->payout_approved_at);
    }

    /**
     * Approve driver payout
     */
    public function approvePayout(User $approver, float $amount = null): void
    {
        $this->update([
            'driver_payout_amount' => $amount ?? $this->driver_payout_amount,
            'payout_approved_at' => now(),
            'payout_approved_by' => $approver->id,
        ]);
    }

    /**
     * Mark as driver paid
     */
    public function markAsPaid(): void
    {
        $this->update(['is_driver_paid' => true]);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope by dispatch type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('dispatch_type', $type);
    }

    /**
     * Scope pickup legs
     */
    public function scopePickups($query)
    {
        return $query->where('dispatch_type', 'P');
    }

    /**
     * Scope delivery legs
     */
    public function scopeDeliveries($query)
    {
        return $query->whereIn('dispatch_type', ['D', 'PD']);
    }

    /**
     * Scope by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope completed legs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope pending legs
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope in progress legs
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['dispatched', 'en_route', 'arrived', 'in_progress']);
    }

    /**
     * Scope unpaid driver payouts
     */
    public function scopeUnpaid($query)
    {
        return $query->where('is_driver_paid', false)
                    ->whereNotNull('driver_payout_amount')
                    ->whereNotNull('completed_at');
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_date', [$startDate, $endDate]);
    }

    /**
     * Scope running late
     */
    public function scopeRunningLate($query)
    {
        return $query->where('scheduled_date', '<=', now()->toDateString())
                    ->whereRaw('TIME(scheduled_time_to) <= ?', [now()->format('H:i:s')])
                    ->where('status', '!=', 'completed');
    }

    /**
     * Scope by driver
     */
    public function scopeByDriver($query, int $driverId)
    {
        return $query->whereHas('trip', function($q) use ($driverId) {
            $q->where('delivery_agent_id', $driverId);
        });
    }
}