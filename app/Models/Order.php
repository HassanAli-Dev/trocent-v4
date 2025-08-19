<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Basic info
        'order_code',
        'quote_code', 
        'is_quote',
        'service_type',
        'customer_id',
        'user_id',
        'status',
        'reference_number',
        'caller',
        
        // Shipper info
        'shipper_name',
        'shipper_contact_name',
        'shipper_email',
        'shipper_phone',
        'shipper_address',
        'shipper_suite',
        'shipper_city',
        'shipper_province',
        'shipper_postal_code',
        'shipper_special_instructions',
        
        // Receiver info
        'receiver_name',
        'receiver_contact_name',
        'receiver_email',
        'receiver_phone',
        'receiver_address',
        'receiver_suite',
        'receiver_city',
        'receiver_province',
        'receiver_postal_code',
        'receiver_special_instructions',
        
        // Crossdock info
        'is_crossdock',
        'crossdock_name',
        'crossdock_contact_name',
        'crossdock_email',
        'crossdock_phone',
        'crossdock_address',
        'crossdock_suite',
        'crossdock_city',
        'crossdock_province',
        'crossdock_postal_code',
        'crossdock_special_instructions',
        
        // Interline info
        'interline_pickup',
        'interline_delivery',
        'interline_id',
        'interline_name',
        'interline_contact_name',
        'interline_email',
        'interline_phone',
        'interline_address',
        'interline_suite',
        'interline_city',
        'interline_province',
        'interline_postal_code',
        'interline_special_instructions',
        
        // Scheduling
        'pickup_date',
        'pickup_time_from',
        'pickup_time_to',
        'pickup_appointment',
        'pickup_appointment_number',
        'pickup_dispatch_notes',
        'delivery_date',
        'delivery_time_from',
        'delivery_time_to',
        'delivery_appointment',
        'delivery_appointment_number',
        'delivery_dispatch_notes',
        
        // Freight totals
        'total_pieces',
        'total_chargeable_pieces',
        'total_weight',
        'total_chargeable_weight',
        'total_volume_weight',
        'actual_weight',
        
        // Manual overrides
        'manual_freight',
        'manual_skids',
        'manual_weight',
        'no_charges',
        
        // Financial totals
        'freight_rate',
        'fuel_surcharge',
        'accessorial_total',
        'sub_total',
        'provincial_tax',
        'federal_tax',
        'grand_total',
        'waiting_time_charge',
        
        // Interline charges
        'interline_charge_name',
        'interline_charge_reference',
        'interline_charge_amount',
        
        // Terminal/warehouse
        'terminal_id',
        
        // Audit & notes
        'internal_notes',
        'audit_number',
        
        // Billing
        'is_invoiced',
        'invoiced_at',
        'invoice_id',
    ];

    protected $casts = [
        'is_quote' => 'boolean',
        'is_crossdock' => 'boolean',
        'interline_pickup' => 'boolean',
        'interline_delivery' => 'boolean',
        'pickup_appointment' => 'boolean',
        'delivery_appointment' => 'boolean',
        'manual_freight' => 'boolean',
        'manual_skids' => 'boolean',
        'manual_weight' => 'boolean',
        'no_charges' => 'boolean',
        'is_invoiced' => 'boolean',
        'pickup_date' => 'date',
        'delivery_date' => 'date',
        'invoiced_at' => 'datetime',
        'pickup_time_from' => 'datetime:H:i',
        'pickup_time_to' => 'datetime:H:i',
        'delivery_time_from' => 'datetime:H:i',
        'delivery_time_to' => 'datetime:H:i',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Order belongs to a customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Order was created by a user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order has many freight line items
     */
    public function freights(): HasMany
    {
        return $this->hasMany(OrderFreight::class);
    }

    /**
     * Order has many accessorial charges
     */
    public function accessorials(): HasMany
    {
        return $this->hasMany(OrderAccessorial::class);
    }

    /**
     * Order has many dispatch orders (legs)
     */
    public function dispatchOrders(): HasMany
    {
        return $this->hasMany(DispatchOrder::class);
    }

    /**
     * Interline company (if using interliner)
     */
    public function interlineCompany(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'interline_id');
    }

    /**
     * Warehouse terminal (if applicable)
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(WarehouseTerminal::class, 'terminal_id');
    }

    /**
     * Invoice (when billed)
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // ==========================================
    // CALCULATED RELATIONSHIPS
    // ==========================================

    /**
     * Get pickup leg dispatch order
     */
    public function pickupLeg(): HasOne
    {
        return $this->hasOne(DispatchOrder::class)
            ->where('dispatch_type', 'P')
            ->where('leg_sequence', 1);
    }

    /**
     * Get delivery leg dispatch order
     */
    public function deliveryLeg(): HasOne
    {
        return $this->hasOne(DispatchOrder::class)
            ->where('dispatch_type', 'D')
            ->orWhere('dispatch_type', 'PD');
    }

    /**
     * Get combined pickup/delivery leg
     */
    public function combinedLeg(): HasOne
    {
        return $this->hasOne(DispatchOrder::class)
            ->where('dispatch_type', 'PD');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope to get only quotes
     */
    public function scopeQuotes($query)
    {
        return $query->where('is_quote', true);
    }

    /**
     * Scope to get only orders (not quotes)
     */
    public function scopeOrders($query)
    {
        return $query->where('is_quote', false);
    }

    /**
     * Scope to get crossdock orders
     */
    public function scopeCrossdock($query)
    {
        return $query->where('is_crossdock', true);
    }

    /**
     * Scope to get orders by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get orders by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('pickup_date', [$startDate, $endDate]);
    }

    // ==========================================
    // BUSINESS LOGIC METHODS
    // ==========================================

    /**
     * Check if order needs separate pickup/delivery legs
     */
    public function needsSeparateLegs(): bool
    {
        return $this->pickup_date != $this->delivery_date 
            || $this->is_crossdock 
            || $this->interline_pickup 
            || $this->interline_delivery;
    }

    /**
     * Get the appropriate dispatch type for leg creation
     */
    public function getDispatchType(): string
    {
        return $this->needsSeparateLegs() ? 'P' : 'PD';
    }

    /**
     * Calculate total accessorial charges
     */
    public function calculateAccessorialTotal(): float
    {
        return $this->accessorials()->sum('amount');
    }

    /**
     * Calculate grand total
     */
    public function calculateGrandTotal(): float
    {
        return $this->freight_rate 
             + $this->fuel_surcharge 
             + $this->calculateAccessorialTotal() 
             + $this->provincial_tax 
             + $this->federal_tax;
    }

    /**
     * Check if order is fully completed
     */
    public function isCompleted(): bool
    {
        return $this->dispatchOrders()
            ->whereIn('status', ['pending', 'dispatched', 'en_route', 'arrived', 'in_progress'])
            ->doesntExist();
    }

    /**
     * Get order progress percentage
     */
    public function getProgressPercentage(): int
    {
        $totalLegs = $this->dispatchOrders()->count();
        if ($totalLegs === 0) return 0;
        
        $completedLegs = $this->dispatchOrders()
            ->where('status', 'completed')
            ->count();
            
        return round(($completedLegs / $totalLegs) * 100);
    }
}