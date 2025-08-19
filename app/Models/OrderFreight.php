<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFreight extends Model
{
    use HasFactory;

    protected $table = 'order_freights';

    protected $fillable = [
        'order_id',
        'freight_type',
        'freight_description',
        'freight_pieces',
        'freight_weight',
        'weight_unit',
        'freight_chargeable_weight',
        'freight_length',
        'freight_width',
        'freight_height',
        'dimension_unit',
        'is_stackable',
        'stackable_value',
        'has_unit_conversion',
    ];

    protected $casts = [
        'freight_pieces' => 'integer',
        'freight_weight' => 'decimal:2',
        'freight_chargeable_weight' => 'decimal:2',
        'freight_length' => 'decimal:2',
        'freight_width' => 'decimal:2',
        'freight_height' => 'decimal:2',
        'is_stackable' => 'boolean',
        'stackable_value' => 'integer',
        'has_unit_conversion' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Freight belongs to an order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ==========================================
    // BUSINESS LOGIC METHODS
    // ==========================================

    /**
     * Calculate dimensional weight in lbs
     */
    public function calculateDimensionalWeight(): float
    {
        // Convert dimensions to inches if needed
        $length = $this->dimension_unit === 'cm' ? $this->freight_length * 0.393701 : $this->freight_length;
        $width = $this->dimension_unit === 'cm' ? $this->freight_width * 0.393701 : $this->freight_width;
        $height = $this->dimension_unit === 'cm' ? $this->freight_height * 0.393701 : $this->freight_height;
        
        // Dimensional weight formula: (L × W × H) / 166 for lbs
        return ($length * $width * $height) / 166;
    }

    /**
     * Get actual weight in lbs
     */
    public function getActualWeightInLbs(): float
    {
        return $this->weight_unit === 'kg' ? $this->freight_weight * 2.20462 : $this->freight_weight;
    }

    /**
     * Get chargeable weight (higher of actual or dimensional)
     */
    public function getChargeableWeight(): float
    {
        $actualWeight = $this->getActualWeightInLbs();
        $dimensionalWeight = $this->calculateDimensionalWeight();
        
        return max($actualWeight, $dimensionalWeight);
    }

    /**
     * Check if freight exceeds standard dimensions
     */
    public function exceedsStandardDimensions(): bool
    {
        // Convert to inches for comparison
        $length = $this->dimension_unit === 'cm' ? $this->freight_length * 0.393701 : $this->freight_length;
        $width = $this->dimension_unit === 'cm' ? $this->freight_width * 0.393701 : $this->freight_width;
        $height = $this->dimension_unit === 'cm' ? $this->freight_height * 0.393701 : $this->freight_height;
        
        // Standard LTL limits: 48" × 40" × 72"
        return $length > 48 || $width > 40 || $height > 72;
    }

    /**
     * Calculate pieces from weight (using customer rule)
     */
    public function calculatePiecesFromWeight(?int $weightToPiecesRule = 1000): int
    {
        if (!$weightToPiecesRule) return $this->freight_pieces;
        
        $weightInLbs = $this->getActualWeightInLbs();
        $pieces = $weightInLbs / $weightToPiecesRule;
        
        // Round up if there's a remainder
        return (fmod($pieces, 1) > 0) ? (int)$pieces + 1 : (int)$pieces;
    }

    /**
     * Calculate pieces from dimensions (based on length)
     */
    public function calculatePiecesFromDimensions(): int
    {
        // Convert length to inches
        $lengthInches = $this->dimension_unit === 'cm' ? $this->freight_length * 0.393701 : $this->freight_length;
        
        // Calculate base pieces from length (48" = 1 piece)
        $basePieces = max(1, ceil($lengthInches / 48)) * $this->freight_pieces;
        
        // Check for width/height overages
        $widthInches = $this->dimension_unit === 'cm' ? $this->freight_width * 0.393701 : $this->freight_width;
        $heightInches = $this->dimension_unit === 'cm' ? $this->freight_height * 0.393701 : $this->freight_height;
        
        // Double pieces if width or height exceeds standard
        if ($widthInches > 48 || $heightInches > 82) {
            $basePieces *= 2;
        }
        
        // Apply stackable multiplier
        if ($this->is_stackable) {
            $basePieces *= 2;
        }
        
        return $basePieces;
    }

    /**
     * Get total volume in cubic inches
     */
    public function getTotalVolume(): float
    {
        // Convert to inches if needed
        $length = $this->dimension_unit === 'cm' ? $this->freight_length * 0.393701 : $this->freight_length;
        $width = $this->dimension_unit === 'cm' ? $this->freight_width * 0.393701 : $this->freight_width;
        $height = $this->dimension_unit === 'cm' ? $this->freight_height * 0.393701 : $this->freight_height;
        
        return $length * $width * $height * $this->freight_pieces;
    }

    /**
     * Format dimensions for display
     */
    public function getFormattedDimensions(): string
    {
        $unit = $this->dimension_unit === 'cm' ? 'cm' : '"';
        return number_format($this->freight_length, 1) . ' × ' . 
               number_format($this->freight_width, 1) . ' × ' . 
               number_format($this->freight_height, 1) . $unit;
    }

    /**
     * Get freight type display name
     */
    public function getFreightTypeDisplay(): string
    {
        return match($this->freight_type) {
            'skid' => 'Skid',
            'box' => 'Box',
            'envelope' => 'Envelope',
            'weight' => 'Weight',
            default => ucfirst($this->freight_type)
        };
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope to get freight by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('freight_type', $type);
    }

    /**
     * Scope to get stackable freight
     */
    public function scopeStackable($query)
    {
        return $query->where('is_stackable', true);
    }

    /**
     * Scope to get oversized freight
     */
    public function scopeOversized($query)
    {
        return $query->where(function($q) {
            $q->where('freight_length', '>', 48)
              ->orWhere('freight_width', '>', 40)
              ->orWhere('freight_height', '>', 72);
        });
    }
}