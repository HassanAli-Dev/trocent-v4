<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;


class UnitConversionService
{
    // Conversion constants - match Electron exactly
    const LBS_TO_KG = 0.453592;
    const KG_TO_LBS = 2.20462;
    const CM_TO_INCH = 0.393701;
    const INCH_TO_CM = 2.54;

    /**
     * FIXED: Main conversion method - replicates Electron's EXACT behavior
     * This handles the complex double conversion logic that Electron uses
     */
    public static function normalizeToElectronStandard(array $freight): array
    {
        $weight = (float) ($freight['freight_weight'] ?? 0);
        $length = (float) ($freight['freight_length'] ?? 0);
        $width = (float) ($freight['freight_width'] ?? 0);
        $height = (float) ($freight['freight_height'] ?? 0);
        $weightUnit = $freight['weight_unit'] ?? 'lbs';
        $dimensionUnit = $freight['dimension_unit'] ?? 'in';
        $hasConversion = $freight['has_unit_conversion'] ?? false;

        // Store original values for display
        $originalWeight = $weight;
        $originalWeightUnit = $weightUnit;

        // Apply conversion toggle logic for WEIGHT CALCULATIONS only
        if ($hasConversion) {
            if ($weightUnit === 'kg' && $dimensionUnit === 'cm') {
                $weight = $weight * self::KG_TO_LBS;
                $weightUnit = 'lbs';
            } elseif ($weightUnit === 'lbs' && $dimensionUnit === 'in') {
                $weight = $weight * self::LBS_TO_KG;
                $weightUnit = 'kg';
            }
        }

        // For size calculations, always convert to inches (for piece calculations)
        if ($dimensionUnit === 'cm') {
            $lengthInches = $length * self::CM_TO_INCH;
            $widthInches = $width * self::CM_TO_INCH;
            $heightInches = $height * self::CM_TO_INCH;
        } else {
            $lengthInches = $length;
            $widthInches = $width;
            $heightInches = $height;
        }

        // Apply stackable logic for size calculations
        $isStackable = $freight['is_stackable'] ?? false;
//        if ($isStackable) {
//            $heightInches = 102; // Always 102 inches for stackable
//        }

        // Final weight conversion for internal calculations
        if ($weightUnit === 'kg') {
            $weight = $weight * self::KG_TO_LBS;
        }

        return [
            'weight_original' => $originalWeight,
            'weight_unit_original' => $originalWeightUnit,
            'weight_lbs' => $weight,  // For rate calculations
            'length_inches' => $lengthInches,  // For size calculations
            'width_inches' => $widthInches,
            'height_inches' => $heightInches,
            'pieces' => (int) ($freight['freight_pieces'] ?? 1),
            'is_stackable' => $isStackable,
        ];
    }


    /**
     * FIXED: Calculate volume weight using Electron's EXACT logic
     * This replicates the exact double conversion behavior from Electron
     */
    public static function calculateVolumeWeight(array $freight): float
    {
        $pieces = (int) ($freight['freight_pieces'] ?? 1);
        $length = (float) ($freight['freight_length'] ?? 0);
        $width = (float) ($freight['freight_width'] ?? 0);
        $height = (float) ($freight['freight_height'] ?? 0);
        $dimensionUnit = $freight['dimension_unit'] ?? 'in';
        $hasConversion = $freight['has_unit_conversion'] ?? false;
        $weightUnit = $freight['weight_unit'] ?? 'lbs';

        //Apply conversion logic to dimensions ONLY if conversion is active
        if ($hasConversion) {
            if ($weightUnit === 'kg' && $dimensionUnit === 'cm') {
                // Convert CM to IN when conversion is active
                $length = $length * 0.393701;
                $width = $width * 0.393701;
                $height = $height * 0.393701;
                $dimensionUnit = 'in';  // Now use inches formula
            } elseif ($weightUnit === 'lbs' && $dimensionUnit === 'in') {
                // Convert IN to CM when conversion is active
                $length = $length / 0.393701;
                $width = $width / 0.393701;
                $height = $height / 0.393701;
                $dimensionUnit = 'cm';  // Now use cm formula
            }
        }

        // Apply stackable logic (before volume calculation)
        $isStackable = $freight['is_stackable'] ?? false;
        if ($isStackable) {
//            if ($dimensionUnit === 'cm') {
//                $height = 102 / 0.393701; // Convert 102 inches to cm
//            } else {
//                $height = 102; // Already in inches
//            }
            $height = 102;
        }


        // This is the key difference - Electron doesn't always convert to inches!
        if ($dimensionUnit == 'in') {
            $volumeWeight = $pieces * (($length * $width * $height) / 172);
        } else {
            $volumeWeight = $pieces * (($length * $width * $height) / 6000);
        }

        return round($volumeWeight, 2);
    }



    /**
     * FIXED: Calculate pieces from size using Electron's EXACT logic
     */
    public static function calculatePiecesFromSize(array $freight): int
    {
        $normalized = self::normalizeToElectronStandard($freight);

        // Round dimensions up like Electron
        $length = ceil($normalized['length_inches']);
        $width = ceil($normalized['width_inches']);
        $height = ceil($normalized['height_inches']);

        $pieces = $normalized['pieces'];

        // Electron's dimensional overage logic
        $newFreightNumber = $pieces;

        if ($length > 48 || $width > 48 || $height > 82) {
            $newFreightNumber = ceil($length / 48) * $pieces;

            if ($width > 48 || $height > 82) {
                $newFreightNumber *= 2;
            }
        }

        // Apply stackable multiplier
        if ($normalized['is_stackable']) {
            $newFreightNumber *= 2;
        }

        return $newFreightNumber;
    }


    /**
     * Calculate pieces from weight using normalized values for calculations
     */
    public static function calculatePiecesFromWeight(array $freight, int $rule): int
    {
        if ($rule <= 0) return (int) ($freight['freight_pieces'] ?? 1);

        $normalized = self::normalizeToElectronStandard($freight);
        $pieces = $normalized['weight_original'] / $rule;

        if (fmod($pieces, 1) > 0) {
            return intval($pieces) + 1;
        }

        return intval($pieces);
    }


    /**
     * FIXED: Get chargeable weight using display-appropriate values
     */
    public static function getChargeableWeight(array $freight): float
    {
        $normalized = self::normalizeToElectronStandard($freight);

        // For display: use original weight value for actual weight
        $actualWeight = $normalized['weight_original'];

        // Calculate volume weight (always in same unit as actual weight for comparison)
        $volumeWeight = self::calculateVolumeWeight($freight);



        return max($actualWeight, $volumeWeight);
    }

    /**
     * FIXED: Calculate order totals with proper unit handling for display
     */
    public static function calculateOrderTotals(array $freights, array $customerRules = []): array
    {
        $totalActualWeight = 0;
        $totalVolumeWeight = 0;  // This will be the pure sum of volume weights
        $totalChargeableWeight = 0;
        $has_skid_type = false;
        $has_weight_type = false;
        $totalPieces = 0;
        $totalChargeablePieces = 0;
        $boxWeight = 0;
        $weightInKg = 0;

        foreach ($freights as $freight) {
            if (empty($freight)) continue;

            $normalized = self::normalizeToElectronStandard($freight);

            // Get weights
            $actualWeight = $normalized['weight_original'];
            $volumeWeight = self::calculateVolumeWeight($freight);

            //Convert volume weight to original unit for proper comparison


            $volumeWeightDisplay = $volumeWeight;
            //Individual chargeable weight (max per item)
            $itemChargeableWeight = max($actualWeight, $volumeWeightDisplay);

            // Calculate pieces
            $actualPieces = $normalized['pieces'];
            if ($freight["freight_type"] === "skid")
            {
                $has_skid_type = true;
                $chargeablePieces = $actualPieces;
                if (isset($customerRules['weight_to_pieces_rule']) && $customerRules['weight_to_pieces_rule'] > 0) {
                    $weightPieces = self::calculatePiecesFromWeight($freight, $customerRules['weight_to_pieces_rule']);
                    $chargeablePieces = max($chargeablePieces, $weightPieces);
                }

                $sizePieces = self::calculatePiecesFromSize($freight);
                $chargeablePieces = max($chargeablePieces, $sizePieces);
                $totalChargeablePieces += $chargeablePieces;
            } else {
                $has_weight_type = true;
                $boxWeight += $itemChargeableWeight;
            }

            $totalActualWeight += $actualWeight;
            $totalVolumeWeight += $volumeWeightDisplay;  // Pure sum of volume weights
            $totalChargeableWeight += $volumeWeightDisplay;  // Sum of max(actual, volume) per item
            $totalPieces += $actualPieces;

            // Weight in KG
            if ($normalized['weight_unit_original'] === 'kg') {
                $weightInKg += $volumeWeight;
            } else {
                $weightInKg += $volumeWeight * self::LBS_TO_KG;
            }
        }


        if ($totalVolumeWeight < $totalActualWeight) {
            $totalChargeableWeight = $totalActualWeight;
        }

        return [
            'total_actual_weight' => round($totalActualWeight, 2),
            'pure_total_volume_weight' => round($totalVolumeWeight, 2),  // ✅ NEW: Pure volume weight sum
            'total_volume_weight' => round($totalVolumeWeight, 2),  // For backward compatibility
            'total_chargeable_weight' => round($totalChargeableWeight, 2),
            'total_pieces' => $totalPieces,
            'total_chargeable_pieces' => $totalChargeablePieces,
            'box_weight' => round($boxWeight, 2),
            'has_skid_type' => $has_skid_type,
            'skid_weight' => round($totalActualWeight, 2),
            'has_weight_type' => $has_weight_type,
            'weight_in_kg' => round($weightInKg, 2),
        ];
    }

    // Simple unit conversion helpers for UI purposes only
    public static function convertLbsToKg(float $weight): float
    {
        return round($weight * self::LBS_TO_KG, 2);
    }

    public static function convertKgToLbs(float $weight): float
    {
        return round($weight * self::KG_TO_LBS, 2);
    }

    public static function convertInchesToCm(float $dimension): float
    {
        return round($dimension * self::INCH_TO_CM, 2);
    }

    public static function convertCmToInches(float $dimension): float
    {
        return round($dimension * self::CM_TO_INCH, 2);
    }


    /**
     * Convert weight between different units
     */
    public static function convertWeight(float $weight, string $fromUnit, string $toUnit): float
    {
        if ($fromUnit === $toUnit) {
            return $weight;
        }

        // Convert to kilograms first (base unit)
        $weightInKg = self::toKilograms($weight, $fromUnit);

        // Convert from kilograms to target unit
        return self::fromKilograms($weightInKg, $toUnit);
    }

    /**
     * Convert weight to kilograms
     */
    private static function toKilograms(float $weight, string $unit): float
    {
        return match (strtolower($unit)) {
            'kg', 'kilograms' => $weight,
            'lbs', 'pounds' => $weight * 0.453592,
            'g', 'grams' => $weight / 1000,
            'oz', 'ounces' => $weight * 0.0283495,
            'tons', 'tonnes' => $weight * 1000,
            default => $weight, // Assume kg if unknown
        };
    }

    /**
     * Convert weight from kilograms
     */
    private static function fromKilograms(float $weightInKg, string $unit): float
    {
        return match (strtolower($unit)) {
            'kg', 'kilograms' => $weightInKg,
            'lbs', 'pounds' => $weightInKg / 0.453592,
            'g', 'grams' => $weightInKg * 1000,
            'oz', 'ounces' => $weightInKg / 0.0283495,
            'tons', 'tonnes' => $weightInKg / 1000,
            default => $weightInKg, // Return kg if unknown
        };
    }

    // ==========================================
    // DIMENSION CONVERSION METHODS
    // ==========================================

    /**
     * Convert dimensions between different units
     */
    public static function convertDimension(float $dimension, string $fromUnit, string $toUnit): float
    {
        if ($fromUnit === $toUnit) {
            return $dimension;
        }

        // Convert to meters first (base unit)
        $dimensionInMeters = self::toMeters($dimension, $fromUnit);

        // Convert from meters to target unit
        return self::fromMeters($dimensionInMeters, $toUnit);
    }

    /**
     * Convert dimension to meters
     */
    private static function toMeters(float $dimension, string $unit): float
    {
        return match (strtolower($unit)) {
            'm', 'meters', 'metres' => $dimension,
            'cm', 'centimeters', 'centimetres' => $dimension / 100,
            'mm', 'millimeters', 'millimetres' => $dimension / 1000,
            'in', 'inches' => $dimension * 0.0254,
            'ft', 'feet' => $dimension * 0.3048,
            'yd', 'yards' => $dimension * 0.9144,
            default => $dimension, // Assume meters if unknown
        };
    }

    /**
     * Convert dimension from meters
     */
    private static function fromMeters(float $dimensionInMeters, string $unit): float
    {
        return match (strtolower($unit)) {
            'm', 'meters', 'metres' => $dimensionInMeters,
            'cm', 'centimeters', 'centimetres' => $dimensionInMeters * 100,
            'mm', 'millimeters', 'millimetres' => $dimensionInMeters * 1000,
            'in', 'inches' => $dimensionInMeters / 0.0254,
            'ft', 'feet' => $dimensionInMeters / 0.3048,
            'yd', 'yards' => $dimensionInMeters / 0.9144,
            default => $dimensionInMeters, // Return meters if unknown
        };
    }

    // ==========================================
    // TIME CONVERSION METHODS (For Accessorials)
    // ==========================================

    /**
     * Convert time between different units
     * Used for time-based accessorial calculations
     */
    public static function convertTime(float $time, string $fromUnit, string $toUnit): float
    {
        if ($fromUnit === $toUnit) {
            return $time;
        }

        // Convert to minutes first (base unit for accessorials)
        $timeInMinutes = self::toMinutes($time, $fromUnit);

        // Convert from minutes to target unit
        return self::fromMinutes($timeInMinutes, $toUnit);
    }

    /**
     * Convert time to minutes (Electron's base unit for waiting time)
     */
    private static function toMinutes(float $time, string $unit): float
    {
        return match (strtolower($unit)) {
            'minute', 'minutes', 'min' => $time,
            'hour', 'hours', 'hr', 'h' => $time * 60,
            'second', 'seconds', 'sec', 's' => $time / 60,
            'day', 'days', 'd' => $time * 1440, // 24 * 60
            default => $time, // Assume minutes if unknown
        };
    }

    /**
     * Convert time from minutes
     */
    private static function fromMinutes(float $timeInMinutes, string $unit): float
    {
        return match (strtolower($unit)) {
            'minute', 'minutes', 'min' => $timeInMinutes,
            'hour', 'hours', 'hr', 'h' => $timeInMinutes / 60,
            'second', 'seconds', 'sec', 's' => $timeInMinutes * 60,
            'day', 'days', 'd' => $timeInMinutes / 1440, // 24 * 60
            default => $timeInMinutes, // Return minutes if unknown
        };
    }

    // ==========================================
    // WAITING TIME CALCULATION METHODS
    // Specific methods for Electron-style time calculations
    // ==========================================

    /**
     * Calculate time difference in minutes between two time strings
     * Matches Electron's calculateTimeDifferenceInMinutes function
     * FIXED: Renamed to avoid confusion with OrderResource method
     */
    public static function calculateTimeDifferenceInMinutes(?string $startTime, ?string $endTime): int
    {
        if (!$startTime || !$endTime || $startTime === '00:00:00' || $endTime === '00:00:00') {
            return 0;
        }

        try {
            $start = Carbon::parse($startTime);
            $end = Carbon::parse($endTime);

            // Calculate difference in minutes
            $diffInMinutes = $end->diffInMinutes($start);

            // Handle case where end time is before start time (next day)
            if ($end->lt($start)) {
                $diffInMinutes = $end->addDay()->diffInMinutes($start);
            }

            return $diffInMinutes;
        } catch (Exception $e) {
            Log::error("Error calculating waiting time in UnitConversionService: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate billable waiting time after deducting free time
     * Used for time-based accessorial calculations
     */
    public static function calculateBillableWaitingTime(
        int $totalWaitingMinutes,
        float $freeTime,
        string $freeTimeUnit = 'minute'
    ): int {
        // Convert free time to minutes
        $freeTimeMinutes = self::toMinutes($freeTime, $freeTimeUnit);

        // Subtract free time, ensuring we don't go below 0
        return max(0, $totalWaitingMinutes - (int)$freeTimeMinutes);
    }

    /**
     * Format minutes as HH:MM for display
     * Matches Electron's formatting
     */
    public static function formatMinutesToHHMM(int $minutes): string
    {
        if ($minutes <= 0) {
            return '00:00';
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $remainingMinutes);
    }

    /**
     * Parse HH:MM format to minutes
     * Useful for converting time inputs to calculation format
     */
    public static function parseHHMMToMinutes(string $timeString): int
    {
        try {
            $time = Carbon::parse($timeString);
            return ($time->hour * 60) + $time->minute;
        } catch (Exception $e) {
            Log::error("Error parsing time string: " . $e->getMessage());
            return 0;
        }
    }

    // ==========================================
    // VOLUME CALCULATION METHODS
    // ==========================================

    /**
     * Calculate volume from dimensions
     */
    public static function calculateVolume(float $length, float $width, float $height, string $unit = 'in'): float
    {
        return $length * $width * $height;
    }

    /**
     * Calculate volumetric weight (dimensional weight)
     * Common in freight calculations
     */
    public static function calculateVolumetricWeight(
        float $length,
        float $width,
        float $height,
        string $dimensionUnit = 'in',
        int $divisor = 166 // Standard air freight divisor
    ): float {
        // Convert dimensions to inches if needed
        $lengthInInches = self::convertDimension($length, $dimensionUnit, 'in');
        $widthInInches = self::convertDimension($width, $dimensionUnit, 'in');
        $heightInInches = self::convertDimension($height, $dimensionUnit, 'in');

        // Calculate volume in cubic inches
        $volumeCubicInches = $lengthInInches * $widthInInches * $heightInInches;

        // Calculate volumetric weight in pounds
        return $volumeCubicInches / $divisor;
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Validate if a unit is supported for a given type
     */
    public static function isValidUnit(string $unit, string $type): bool
    {
        $validUnits = [
            'weight' => ['kg', 'kilograms', 'lbs', 'pounds', 'g', 'grams', 'oz', 'ounces', 'tons', 'tonnes'],
            'dimension' => ['m', 'meters', 'metres', 'cm', 'centimeters', 'centimetres', 'mm', 'millimeters', 'millimetres', 'in', 'inches', 'ft', 'feet', 'yd', 'yards'],
            'time' => ['minute', 'minutes', 'min', 'hour', 'hours', 'hr', 'h', 'second', 'seconds', 'sec', 's', 'day', 'days', 'd'],
        ];

        return in_array(strtolower($unit), $validUnits[$type] ?? []);
    }

    /**
     * Get display name for unit
     */
    public static function getUnitDisplayName(string $unit): string
    {
        $displayNames = [
            // Weight
            'kg' => 'Kilograms',
            'lbs' => 'Pounds',
            'g' => 'Grams',
            'oz' => 'Ounces',
            'tons' => 'Tons',

            // Dimensions
            'm' => 'Meters',
            'cm' => 'Centimeters',
            'mm' => 'Millimeters',
            'in' => 'Inches',
            'ft' => 'Feet',
            'yd' => 'Yards',

            // Time
            'min' => 'Minutes',
            'hour' => 'Hours',
            'sec' => 'Seconds',
            'day' => 'Days',
        ];

        return $displayNames[strtolower($unit)] ?? ucfirst($unit);
    }

    // ==========================================
    // ACCESSORIAL-SPECIFIC HELPER METHODS
    // ==========================================

    /**
     * Calculate total waiting time from pickup and delivery times
     * Mirrors Electron's getLatestPickupAndDeliveryTimes function
     */
    public static function calculateTotalWaitingTime(
        ?string $pickupInTime,
        ?string $pickupOutTime,
        ?string $deliveryInTime,
        ?string $deliveryOutTime,
        bool $excludePickup = false,
        bool $excludeDelivery = false
    ): array {

        $pickupWaitingTime = 0;
        $deliveryWaitingTime = 0;

        // Calculate pickup waiting time
        if (!$excludePickup) {
            $pickupWaitingTime = self::calculateTimeDifferenceInMinutes($pickupInTime, $pickupOutTime);
        }

        // Calculate delivery waiting time
        if (!$excludeDelivery) {
            $deliveryWaitingTime = self::calculateTimeDifferenceInMinutes($deliveryInTime, $deliveryOutTime);
        }

        $totalWaitingTime = $pickupWaitingTime + $deliveryWaitingTime;

        return [
            'pickup_waiting_time' => $pickupWaitingTime,
            'delivery_waiting_time' => $deliveryWaitingTime,
            'total_waiting_time' => $totalWaitingTime,
            'pickup_waiting_formatted' => self::formatMinutesToHHMM($pickupWaitingTime),
            'delivery_waiting_formatted' => self::formatMinutesToHHMM($deliveryWaitingTime),
            'total_waiting_formatted' => self::formatMinutesToHHMM($totalWaitingTime),
        ];
    }

    /**
     * Apply free time deduction per leg (Electron's logic)
     * Each pickup and delivery leg gets its own free time allowance
     */
    public static function calculateBillableTimeWithPerLegFreeTime(
        int $pickupWaitingTime,
        int $deliveryWaitingTime,
        float $freeTimePerLeg,
        string $freeTimeUnit = 'minute'
    ): array {

        $freeTimeMinutes = self::toMinutes($freeTimePerLeg, $freeTimeUnit);

        $billablePickupTime = max(0, $pickupWaitingTime - $freeTimeMinutes);
        $billableDeliveryTime = max(0, $deliveryWaitingTime - $freeTimeMinutes);
        $totalBillableTime = $billablePickupTime + $billableDeliveryTime;

        return [
            'billable_pickup_time' => $billablePickupTime,
            'billable_delivery_time' => $billableDeliveryTime,
            'total_billable_time' => $totalBillableTime,
            'free_time_minutes' => $freeTimeMinutes,
        ];
    }
}
