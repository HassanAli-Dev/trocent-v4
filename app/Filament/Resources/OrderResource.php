<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\DeliveryAgent;
use Filament\Schemas\Components\Utilities\Get;
use Exception;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\FuelSurcharge;
use App\Models\Order;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Cache;
use App\Services\UnitConversionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Models\AddressBook;


class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $modelLabel = 'Order';

    protected static ?string $pluralModelLabel = 'Orders';

    protected static ?int $navigationSort = 1;

    private const INTERNAL = 'I';
    private const EXTERNAL = 'E';
    private const LTL_BRACKET = 'ltl';

    public static function form(Schema $schema): Schema
    {
        // Basic form - actual forms are in custom pages
        return $schema
            ->components([
                TextInput::make('order_code')
                    ->required()
                    ->maxLength(50),
                Select::make('customer_id')
                    ->relationship('customer', 'full_name')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_code')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable(),

                BadgeColumn::make('service_type')
                    ->label('Service')
                    ->colors([
                        'success' => 'regular',
                        'warning' => 'direct',
                        'danger' => 'rush',
                    ]),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'pending',
                        'info' => 'quote',
                        'primary' => 'entered',
                        'warning' => 'dispatched',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                    ]),

                TextColumn::make('shipper_city')
                    ->label('Origin'),

                TextColumn::make('receiver_city')
                    ->label('Destination'),

                TextColumn::make('pickup_date')
                    ->label('Pickup')
                    ->date(),

                TextColumn::make('delivery_date')
                    ->label('Delivery')
                    ->date(),

                TextColumn::make('total_pieces')
                    ->label('Pieces')
                    ->numeric(),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('CAD'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'quote' => 'Quote',
                        'entered' => 'Entered',
                        'dispatched' => 'Dispatched',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('service_type')
                    ->options([
                        'regular' => 'Regular',
                        'direct' => 'Direct',
                        'rush' => 'Rush',
                    ]),

                SelectFilter::make('is_quote')
                    ->label('Type')
                    ->options([
                        '0' => 'Order',
                        '1' => 'Quote',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Add relation managers here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    /**
     * Create a reusable AddressBook SearchableInput component
     */
    public static function createAddressBookSearchableInput(
        string $fieldName,
        string $fieldPrefix,
        bool $isRequired = true,
        ?string $defaultName = null
    ): Select {
        return Select::make($fieldName)
            ->label(ucfirst(str_replace('_', ' ', str_replace('_name', '', $fieldName))))
            ->placeholder('Type name...')
            ->default($defaultName)
            ->searchable()
            ->preload()
        ->getOptionLabelUsing(fn ($value) => ($value))
        ->getSearchResultsUsing(function (string $search): array {
                if (strlen($search) < 2) return [];

                $cacheKey = "address_book_search:" . md5($search);
                return Cache::remember($cacheKey, 300, function () use ($search) {
                    return \App\Models\AddressBook::select('name')
                        ->where('name', 'like', $search . '%')
                        ->orderBy('name')
                        ->limit(15)
                        ->pluck('name', 'name')
                        ->toArray();
                });
            })
            ->live(debounce: 250)
            ->afterStateUpdated(function (Set $set, $state) use ($fieldPrefix) {
                if ($state && strlen($state) >= 2) {
                    $addressBook = \App\Models\AddressBook::where('name', $state)->first();

                    if ($addressBook) {
                        static::populateAddressBookFields($set, $fieldPrefix, $addressBook);
                    } else {
                        static::clearAddressBookFields($set, $fieldPrefix);
                    }
                }
            })
            ->required($isRequired);
    }

    /**
     * Populate address book fields with database data
     */
    public static function populateAddressBookFields(Set $set, string $prefix, $addressBook): void
    {
        $set("{$prefix}_name", $addressBook->name);
        $set("{$prefix}_contact_name", $addressBook->contact_name);
        $set("{$prefix}_email", $addressBook->email);
        $set("{$prefix}_phone", $addressBook->phone);
        $set("{$prefix}_address", $addressBook->address);
        $set("{$prefix}_suite", $addressBook->suite);
        $set("{$prefix}_city", $addressBook->city);
        $set("{$prefix}_province", $addressBook->province);
        $set("{$prefix}_postal_code", $addressBook->postal_code);
        $set("{$prefix}_special_instructions", $addressBook->special_instructions);

        // Handle appointment settings for shipper (which affects pickup)
        if ($prefix === 'shipper') {
            if ($addressBook->requires_appointment) {
                $set('pickup_appointment', true);
                $set('pickup_time_from', $addressBook->operating_hours_from ? $addressBook->operating_hours_from->format('H:i') : '00:00');
                $set('pickup_time_to', $addressBook->operating_hours_to ? $addressBook->operating_hours_to->format('H:i') : '00:00');
            } else {
                $set('pickup_appointment', false);
            }
        }

        // Handle appointment settings for receiver (which affects delivery)
        if ($prefix === 'receiver') {
            if ($addressBook->requires_appointment) {
                $set('delivery_appointment', true);
                $set('delivery_time_from', $addressBook->operating_hours_from ? $addressBook->operating_hours_from->format('H:i') : '00:00');
                $set('delivery_time_to', $addressBook->operating_hours_to ? $addressBook->operating_hours_to->format('H:i') : '00:00');
            } else {
                $set('delivery_appointment', false);
            }
        }
    }

    /**
     * Clear address book fields
     */
    public static function clearAddressBookFields(Set $set, string $prefix): void
    {
        $set("{$prefix}_contact_name", null);
        $set("{$prefix}_email", null);
        $set("{$prefix}_phone", null);
        $set("{$prefix}_address", null);
        $set("{$prefix}_suite", null);
        $set("{$prefix}_city", null);
        $set("{$prefix}_province", null);
        $set("{$prefix}_postal_code", null);
        $set("{$prefix}_special_instructions", null);

        // Clear appointment settings when clearing shipper
        if ($prefix === 'shipper') {
            $set('pickup_appointment', false);
        }

        // Clear appointment settings when clearing receiver
        if ($prefix === 'receiver') {
            $set('delivery_appointment', false);
        }
    }

    /**
     * Create a reusable DeliveryAgent SearchableInput component for interliners
     */
    public static function createDeliveryAgentSearchableInput(
        string $fieldName,
        string $fieldPrefix,
        bool $isRequired = true,
        ?string $defaultName = null
    ): Select {
        return Select::make($fieldName)
            ->label(ucfirst(str_replace('_', ' ', str_replace('_name', '', $fieldName))))
            ->placeholder('Type interliner name...')
            ->default($defaultName)
            ->afterStateHydrated(function (Set $set, $state) use ($fieldPrefix) {
                // Auto-lookup when form loads with default values
                if ($state && strlen($state) >= 2) {
                    $deliveryAgent = DeliveryAgent::where('type', 'interliner')
                        ->where('name', $state)
                        ->first();

                    if ($deliveryAgent) {
                        static::populateDeliveryAgentFields($set, $fieldPrefix, $deliveryAgent);
                    }
                }
            })
            ->getSearchResultsUsing(function (string $search): array {
                if (strlen($search) < 2) return [];

                $cacheKey = "delivery_agents_search:" . md5($search);
                return Cache::remember($cacheKey, 300, function () use ($search) {
                    return DeliveryAgent::select('name')
                        ->where('type', 'interliner')
                        ->where('name', 'like', $search . '%')
                        ->orderBy('name')
                        ->limit(15)
                        ->pluck('name', 'name')
                        ->toArray();
                });
            })
            ->afterStateUpdated(function (Set $set, $state) use ($fieldPrefix) {
                if ($state && strlen($state) >= 2) {
                    $deliveryAgent = DeliveryAgent::where('type', 'interliner')
                        ->where('name', $state)
                        ->first();

                    if ($deliveryAgent) {
                        static::populateDeliveryAgentFields($set, $fieldPrefix, $deliveryAgent);
                    } else {
                        static::clearDeliveryAgentFields($set, $fieldPrefix);
                    }
                }
            })
            ->required($isRequired);
    }

    /**
     * Populate delivery agent fields with database data
     */
    public static function populateDeliveryAgentFields(Set $set, string $prefix, $deliveryAgent): void
    {
        $set("{$prefix}_id", $deliveryAgent->id);
        $set("{$prefix}_contact_name", $deliveryAgent->contact_name);
        $set("{$prefix}_email", $deliveryAgent->email);
        $set("{$prefix}_phone", $deliveryAgent->phone);
        $set("{$prefix}_address", $deliveryAgent->address);
        $set("{$prefix}_suite", $deliveryAgent->suite);
        $set("{$prefix}_city", $deliveryAgent->city);
        $set("{$prefix}_province", $deliveryAgent->province);
        $set("{$prefix}_postal_code", $deliveryAgent->postal_code);
    }

    /**
     * Clear delivery agent fields
     */
    public static function clearDeliveryAgentFields(Set $set, string $prefix): void
    {
        $set("{$prefix}_id", null);
        $set("{$prefix}_contact_name", null);
        $set("{$prefix}_email", null);
        $set("{$prefix}_phone", null);
        $set("{$prefix}_address", null);
        $set("{$prefix}_suite", null);
        $set("{$prefix}_city", null);
        $set("{$prefix}_province", null);
        $set("{$prefix}_postal_code", null);
    }





    // 1. COMPLETE VOLUME WEIGHT CALCULATOR WITH CONVERSION
    public static function calculateVolumeWeight(Get $get, Set $set): void
    {

        $freightData = [
            'freight_pieces' => (int) ($get('freight_pieces') ?? 1),
            'freight_weight' => (float) ($get('freight_weight') ?? 0),
            'weight_unit' => $get('weight_unit') ?? 'lbs',
            'freight_length' => (float) ($get('freight_length') ?? 0),
            'freight_width' => (float) ($get('freight_width') ?? 0),
            'freight_height' => (float) ($get('freight_height') ?? 0),
            'dimension_unit' => $get('dimension_unit') ?? 'in',
            'is_stackable' => $get('is_stackable') ?? false,
            'has_unit_conversion' => $get('has_unit_conversion') ?? false,
        ];


        $volumeWeight = UnitConversionService::calculateVolumeWeight($freightData);


        $set('freight_chargeable_weight', $volumeWeight);
    }

    // FIXED: Weight Conversion in CreateOrder.php - Correct Direction
    public static function calculateOrderTotals(array $formState, array $customerData = []): array
    {
        $freights = $formState['freights'] ?? [];

        if (empty($freights)) {
            return [
                'total_actual_weight' => 0,
                'total_volume_weight' => 0,
                'total_chargeable_weight' => 0,
                'total_pieces' => 0,
                'total_chargeable_pieces' => 0,
                'box_weight' => 0,
                'skid_weight' => 0,
                'weight_in_kg' => 0,
            ];
        }

        // Extract customer rules for calculations
        $customerRules = [];
        if (isset($customerData['customer'])) {
            $customerRules['weight_to_pieces_rule'] = $customerData['customer']->weight_to_pieces_rule ?? 1000;
        }

        // ✅ FIX: Use centralized service and return its keys directly
        return UnitConversionService::calculateOrderTotals($freights, $customerRules);
    }
    public static function calculateCurrentFreightWeight(Get $get): float
    {
        $freight = [
            'freight_pieces' => $get('freight_pieces') ?: 1,
            'freight_weight' => $get('freight_weight') ?: 0,
            'freight_length' => $get('freight_length') ?: 0,
            'freight_width' => $get('freight_width') ?: 0,
            'freight_height' => $get('freight_height') ?: 0,
            'weight_unit' => $get('weight_unit') ?: 'lbs',
            'dimension_unit' => $get('dimension_unit') ?: 'in',
            'is_stackable' => $get('is_stackable') ?: false,
            'has_unit_conversion' => $get('has_unit_conversion') ?: false,
        ];

        return UnitConversionService::getChargeableWeight($freight);
    }

    public static function calculateCurrentFreightPieces(Get $get, array $customerData = []): int
    {
        $freight = [
            'freight_pieces' => $get('freight_pieces') ?: 1,
            'freight_weight' => $get('freight_weight') ?: 0,
            'freight_length' => $get('freight_length') ?: 0,
            'freight_width' => $get('freight_width') ?: 0,
            'freight_height' => $get('freight_height') ?: 0,
            'weight_unit' => $get('weight_unit') ?: 'lbs',
            'dimension_unit' => $get('dimension_unit') ?: 'in',
            'is_stackable' => $get('is_stackable') ?: false,
            'has_unit_conversion' => $get('has_unit_conversion') ?: false,
        ];

        $actualPieces = (int) $freight['freight_pieces'];
        $chargeablePieces = $actualPieces;

        // Apply weight-to-pieces rule if configured
        if (isset($customerData['customer']) && $customerData['customer']->weight_to_pieces > 0) {
            $weightPieces = UnitConversionService::calculatePiecesFromWeight(
                $freight,
                $customerData['customer']->weight_to_pieces
            );
            $chargeablePieces = max($chargeablePieces, $weightPieces);
        }

        // Apply size-to-pieces calculation
        $sizePieces = UnitConversionService::calculatePiecesFromSize($freight);
        $chargeablePieces = max($chargeablePieces, $sizePieces);

        return $chargeablePieces;
    }


    // 2. CONVERSION TOGGLE HANDLER
    public static function handleConversionToggle(Get $get, Set $set): void
    {
        self::calculateVolumeWeight($get, $set);
    }


    // 3. UNIT SYNCHRONIZATION METHODS
    public static function syncUnitsFromWeight(Get $get, Set $set): void
    {
        $weightUnit = $get('weight_unit') ?? 'lbs';
        $hasConversion = $get('has_unit_conversion') ?? false;

        // Don't auto-sync if conversion is active (user controls both units)
        if (!$hasConversion) {
            if ($weightUnit === 'kg') {
                $set('dimension_unit', 'cm');
            } else {
                $set('dimension_unit', 'in');
            }
        }
    }

    public static function syncUnitsFromDimension(Get $get, Set $set): void
    {
        $dimensionUnit = $get('dimension_unit') ?? 'in';
        $hasConversion = $get('has_unit_conversion') ?? false;

        // Don't auto-sync if conversion is active (user controls both units)
        if (!$hasConversion) {
            if ($dimensionUnit === 'cm') {
                $set('weight_unit', 'kg');
            } else {
                $set('weight_unit', 'lbs');
            }
        }
    }

    // 5. WEIGHT-TO-PIECES CALCULATION (Electron's logic)
    public static function weightToPieces(float $weight, int $rule): int
    {
        // Create freight array for the service
        $freight = [
            'freight_pieces' => 1,
            'freight_weight' => $weight,
            'weight_unit' => 'lbs', // Assume already converted
            'has_unit_conversion' => false,
        ];

        return UnitConversionService::calculatePiecesFromWeight($freight, $rule);
    }

    // 6. SIZE-TO-PIECES WITH CONVERSION (Electron's logic)
    public static function sizeToPieces(array $freight): int
    {
        return UnitConversionService::calculatePiecesFromSize($freight);
    }

    // 7. ELECTRON'S WEIGHT BRACKET LOGIC
    public static function getWeightBracket(float $weight): string
    {
        // Electron's exact weight bracket logic
        if ($weight > 1 && $weight < 499) {
            return 'ltl';
        } elseif ($weight >= 499 && $weight < 999) {
            return '500';
        } elseif ($weight >= 1000 && $weight < 1999) {
            return '1000';
        } elseif ($weight >= 2000 && $weight < 2999) {
            return '2000';
        } elseif ($weight >= 3000 && $weight < 3999) {
            return '3000';
        } elseif ($weight >= 4000 && $weight < 4999) {
            return '4000';
        } elseif ($weight >= 5000) {
            return '5000';
        }

        return 'ltl';
    }

    public static function calculateFreightRates(array $totals, array $selectedCustomerData, array $formState): array
    {
        if($formState["no_charges"]) return ['freight_rate' => 0];
        if($formState['manual_charges']) return ['freight_rate' => $formState['freight_rate_amount']];

        // Feature flag check
        if (!config('ratesheet.use_new_engine', true)) {
            // Fallback to old engine if needed
            return self::calculateFreightRatesLegacy($totals, $selectedCustomerData, $formState);
        }

        if (!isset($selectedCustomerData['rate_sheets'])) {
            return ['freight_rate' => 0, 'fuel_surcharge' => 0, 'taxes' => ['pst' => 0, 'gst' => 0]];
        }

        $originCity = self::normalizeCityName($formState['shipper_city'] ?? '');
        $destinationCity = self::normalizeCityName($formState['receiver_city'] ?? '');

        if (empty($originCity) || empty($destinationCity)) {
            return ['freight_rate' => 0, 'fuel_surcharge' => 0, 'taxes' => ['pst' => 0, 'gst' => 0]];
        }

        $debugInfo = config('ratesheet.debug_mode', false) ? [] : null;
        $startTime = microtime(true);

        // Calculate rates
        $skidRate = 0;
        $skidDebug = null;
        if ($totals['skid_weight'] > 0 && $totals['has_skid_type']) {
            $result = self::findRateElectronStyle(
                'skid',
                $originCity,
                $destinationCity,
                $totals['skid_weight'],
                $totals['total_chargeable_pieces'],
                $selectedCustomerData,
                $debugInfo
            );
            $skidRate = is_array($result) ? $result['rate'] : $result;
            if ($skidRate < 1)
            {
                $result = self::findRateElectronStyle(
                    'weight',
                    $originCity,
                    $destinationCity,
                    $totals['box_weight'],
                    0,
                    $selectedCustomerData,
                    $debugInfo
                );
                $skidRate = is_array($result) ? $result['rate'] : $result;
                $skidRate = $skidRate * $totals["total_chargeable_pieces"];
            }

            $skidDebug = is_array($result) ? $result['debug'] : null;
        }

        $weightRate = 0;
        $weightDebug = null;
        if ($totals['box_weight'] > 0 && $totals['has_weight_type']) {
            $result = self::findRateElectronStyle(
                'weight',
                $originCity,
                $destinationCity,
                $totals['box_weight'],
                0,
                $selectedCustomerData,
                $debugInfo
            );

            $weightRate = is_array($result) ? $result['rate'] : $result;
            if ($weightRate < 1) {
                $result = self::findRateElectronStyle(
                    'skid',
                    $originCity,
                    $destinationCity,
                    $totals['skid_weight'],
                    $totals['total_chargeable_pieces'],
                    $selectedCustomerData,
                    $debugInfo
                );
                $weightRate = is_array($result) ? $result['rate'] : $result;
                $weightRate = $totals["total_chargeable_pieces"] > 0 ? ($weightRate / $totals["total_chargeable_pieces"]) : $weightRate;
            }

            $weightDebug = is_array($result) ? $result['debug'] : null;
        }

        $totalFreightRate = $skidRate + $weightRate;

        $calculationTime = microtime(true) - $startTime;

        // Log calculation if enabled
        if (config('ratesheet.logging.log_rate_calculations', false)) {
            Log::info('Rate calculation completed', [
                'customer_id' => $selectedCustomerData['customer']->id ?? null,
                'origin' => $originCity,
                'destination' => $destinationCity,
                'skid_rate' => $skidRate,
                'weight_rate' => $weightRate,
                'total_rate' => $totalFreightRate,
                'calculation_time_ms' => round($calculationTime * 1000, 2)
            ]);
        }

        $result = [
            'freight_rate' => $totalFreightRate
        ];

        // Add debug info if enabled
        if ($debugInfo !== null) {
            $result['debug'] = [
                'calculation_time_ms' => round($calculationTime * 1000, 2),
                'skid_calculation' => $skidDebug,
                'weight_calculation' => $weightDebug,
                'engine' => 'enhanced'
            ];
        }

        return $result;
    }

    /**
     * FINAL: Enhanced rate lookup with debug support
     */
    private static function findRateElectronStyle(
        string $type,
        string $originCity,
        string $destinationCity,
        float $weight,
        int $pieces,
        array $selectedCustomerData,
        array &$debugInfo = null
    ) {
        $originalType = $type;

        // Step 1: Check for skid_by_weight
        if ($type === 'skid') {
            $hasSkidByWeight = self::checkSkidByWeightFromCache($selectedCustomerData);
            if ($hasSkidByWeight) {
                $type = 'skid2';
            }
        }

        $debug = $debugInfo !== null ? [
            'original_type' => $originalType,
            'final_type' => $type,
            'weight' => $weight,
            'pieces' => $pieces,
            'bracket' => null,
            'rate_sheet_used' => null,
            'calculation_path' => []
        ] : null;

        // Step 2: Forward lookup
        $forwardResult = self::findDirectionalRate(
            $type,
            $originCity,
            $weight,
            $pieces,
            $selectedCustomerData,
            $debug
        );


        if ($forwardResult['rate'] > 0) {
            if ($debug) $debug['calculation_path'][] = 'forward_lookup_success';

            $returnResult = self::findPairedReturnRate(
                $type,
                $destinationCity,
                $forwardResult['rate_code'],
                $forwardResult['external'],
                $weight,
                $pieces,
                $selectedCustomerData,
                $debug
            );

            $finalRate = max($forwardResult['rate'], $returnResult['rate']);

            if ($debug) {
                $debug['forward_rate'] = $forwardResult['rate'];
                $debug['return_rate'] = $returnResult['rate'];
                $debug['final_rate'] = $finalRate;
            }

            if (isset($returnResult['has_rate_sheet']) && $returnResult['has_rate_sheet']) {
                return $debugInfo !== null ? ['rate' => $finalRate, 'debug' => $debug] : $finalRate;
            }
        }

        // Step 3: Reverse lookup
        if ($debug) $debug['calculation_path'][] = 'trying_reverse_lookup';

        if ($type == "weight")
        {
            return 0;
        }

        $reverseResult = self::findDirectionalRate($type, $destinationCity, $weight, $pieces, $selectedCustomerData, $debug);

        if ($reverseResult['rate'] > 0) {
            if ($debug) $debug['calculation_path'][] = 'reverse_lookup_success';

            $returnResult = self::findPairedReturnRate(
                $type,
                $originCity,
                $reverseResult['rate_code'],
                $reverseResult['external'],
                $weight,
                $pieces,
                $selectedCustomerData,
                $debug
            );

            $finalRate = max($reverseResult['rate'], $returnResult['rate']);

            if ($debug) {
                $debug['reverse_rate'] = $reverseResult['rate'];
                $debug['return_rate'] = $returnResult['rate'];
                $debug['final_rate'] = $finalRate;
            }

            return $debugInfo !== null ? ['rate' => $finalRate, 'debug' => $debug] : $finalRate;
        }

        if ($debug) {
            $debug['calculation_path'][] = 'no_rate_found';
            $debug['final_rate'] = 0;
        }

        return $debugInfo !== null ? ['rate' => 0, 'debug' => $debug] : 0;
    }

    /**
     * FINAL: Find directional rate with debug support
     */
    private static function findDirectionalRate(
        string $type,
        string $originCity,
        float $weight,
        int $pieces,
        array $selectedCustomerData,
        array &$debug = null
    ): array {
        $rateSheets = $selectedCustomerData['rate_sheets'][$type] ?? [];
        $cityRates = $rateSheets[$originCity] ?? [];

        if (empty($cityRates)) {
            if ($debug) $debug['calculation_path'][] = "no_rates_for_city_{$originCity}";
            return ['rate' => 0, 'rate_code' => '', 'external' => self::INTERNAL];
        }

        // Sort by priority
        usort($cityRates, function ($a, $b) {
            return ($b['priority_sequence'] ?? 0) <=> ($a['priority_sequence'] ?? 0);
        });

        $bracket = self::getBracketForType($type, $weight, $pieces, $selectedCustomerData);

        if ($debug) {
            $debug['bracket'] = $bracket;
            $debug['available_sheets'] = count($cityRates);
        }

        // Find matching rate
        foreach ($cityRates as $rateSheet) {
            $rate = self::findRateInMeta($rateSheet['meta'] ?? [], $bracket);

            if ($rate > 0) {
                $calculatedRate = self::calculateFinalRate($rate, $weight, $pieces, $rateSheet, $type);

                if ($debug) {
                    $debug['rate_sheet_used'] = $rateSheet['id'] ?? 'unknown';
                    $debug['base_rate'] = $rate;
                    $debug['calculated_rate'] = $calculatedRate;
                    $debug['min_rate_applied'] = $calculatedRate > $rate;
                }

                return [
                    'rate' => $calculatedRate,
                    'rate_code' => $rateSheet['rate_code'] ?? '',
                    'external' => $rateSheet['external'] ?? self::INTERNAL
                ];
            }

            // Check for minimum rate fallback
            if (($rateSheet['min_rate'] ?? 0) > 0) {
                if ($debug) {
                    $debug['rate_sheet_used'] = $rateSheet['id'] ?? 'unknown';
                    $debug['min_rate_fallback'] = true;
                    $debug['calculated_rate'] = $rateSheet['min_rate'];
                }

                return [
                    'rate' => (float) $rateSheet['min_rate'],
                    'rate_code' => $rateSheet['rate_code'] ?? '',
                    'external' => $rateSheet['external'] ?? self::INTERNAL
                ];
            }
        }

        if ($debug) $debug['calculation_path'][] = 'no_matching_bracket_or_min_rate';
        return ['rate' => 0, 'rate_code' => '', 'external' => self::INTERNAL];
    }

    /**
     * FINAL: Optimized paired return rate search with debug
     */
    private static function findPairedReturnRate(
        string $type,
        string $destinationCity,
        string $rateCode,
        string $externalFlag,
        float $weight,
        int $pieces,
        array $selectedCustomerData,
        array &$debug = null
    ): array {
        $rateSheets = $selectedCustomerData['rate_sheets'][$type] ?? [];
        $cityRates = $rateSheets[$destinationCity] ?? [];

        if (empty($cityRates)) {
            if ($debug) $debug['calculation_path'][] = "no_return_rates_for_{$destinationCity}";
            return ['rate' => 0];
        }

        $bracket = self::getBracketForType($type, $weight, $pieces, $selectedCustomerData);

        // Optimized single search
        $matchingRates = [];

        $hasRateSheet = false;
        foreach ($cityRates as $rateSheet) {
            if (($rateSheet['rate_code'] ?? '') === $rateCode) {
                $hasRateSheet = true;
                $rate = self::findRateInMeta($rateSheet['meta'] ?? [], $bracket);
                if ($rate > 0) {
                    $calculatedRate = self::calculateFinalRate($rate, $weight, $pieces, $rateSheet, $type);
                    $matchingRates[] = [
                        'rate' => $calculatedRate,
                        'external' => $rateSheet['external'] ?? self::INTERNAL,
                        'priority' => $rateSheet['priority_sequence'] ?? 0,
                        'sheet_id' => $rateSheet['id'] ?? 'unknown'
                    ];
                }
            }
        }

        if (empty($matchingRates)) {
            if ($debug) $debug['calculation_path'][] = 'no_matching_return_rates';
            return ['rate' => 0, "has_rate_sheet" => $hasRateSheet];
        }

        // Apply priority logic
        if ($externalFlag === self::EXTERNAL) {
            foreach ($matchingRates as $rate) {
                if ($rate['external'] === self::EXTERNAL) {
                    if ($debug) {
                        $debug['calculation_path'][] = 'external_return_rate_found';
                        $debug['return_sheet_id'] = $rate['sheet_id'];
                    }
                    return ['rate' => $rate['rate']];
                }
            }
            foreach ($matchingRates as $rate) {
                if ($rate['external'] === self::INTERNAL) {
                    if ($debug) {
                        $debug['calculation_path'][] = 'internal_return_rate_fallback';
                        $debug['return_sheet_id'] = $rate['sheet_id'];
                    }
                    return ['rate' => $rate['rate']];
                }
            }
        } else {
            usort($matchingRates, function ($a, $b) {
                $aExternal = $a['external'] === self::EXTERNAL ? 1 : 0;
                $bExternal = $b['external'] === self::EXTERNAL ? 1 : 0;

                if ($aExternal !== $bExternal) {
                    return $bExternal <=> $aExternal;
                }

                return $b['priority'] <=> $a['priority'];
            });

            if ($debug) {
                $debug['calculation_path'][] = 'priority_sorted_return_rate';
                $debug['return_sheet_id'] = $matchingRates[0]['sheet_id'];
            }

            return ['rate' => $matchingRates[0]['rate'], 'has_rate_sheet' => $hasRateSheet];
        }

        return ['rate' => 0, 'has_rate_sheet' => false];
    }


    /**
     * MISSING METHOD 1: getBracketForType
     */
    private static function getBracketForType(string $type, float $weight, int $pieces, array $selectedCustomerData): string
    {
        $availableBrackets = $selectedCustomerData['rate_sheets'][$type]['available_brackets'] ?? [];

        if ($type === 'skid') {
            // Skid rates use PIECES for bracket determination
            return self::getPieceBracket($pieces, $availableBrackets);
        } else {
            // Weight/skid2 rates use WEIGHT for bracket determination
            return self::getWeightBracket($weight);
        }
    }



    /**
     * MISSING METHOD 3: findRateInMeta
     */
    private static function findRateInMeta(array $meta, string $bracket): float
    {
        foreach ($meta as $metaEntry) {
            if (($metaEntry['name'] ?? '') === $bracket) {
                return (float) ($metaEntry['value'] ?? 0);
            }
        }
        return 0;
    }

    /**
     * MISSING METHOD 4: calculateFinalRate
     */
    private static function calculateFinalRate(float $rate, float $weight, int $pieces, array $rateSheet, string $type): float
    {
        $minRate = (float) ($rateSheet['min_rate'] ?? 0);

        if ($type === 'skid') {
            // Check if this is skid_by_weight
            if ($rateSheet['skid_by_weight'] ?? false) {
                $calculatedRate = ($rate * $weight) / 100;
            } else {
                $calculatedRate = $rate * $pieces;
            }
        } else {
            // Weight-based calculation (weight and skid2)
            $calculatedRate = ($rate * $weight) / 100;
        }

        // Apply minimum rate
        return max($calculatedRate, $minRate);
    }

    /**
     * MISSING METHOD 5: checkSkidByWeightFromCache
     */
    private static function checkSkidByWeightFromCache(array $selectedCustomerData): bool
    {
        // Check if any skid rate sheets have skid_by_weight = true
        $skidRates = $selectedCustomerData['rate_sheets']['skid'] ?? [];

        foreach ($skidRates as $cityKey => $cityRates) {
            if ($cityKey === 'available_brackets') continue; // Skip metadata

            if (is_array($cityRates)) {
                foreach ($cityRates as $rateSheet) {
                    if (($rateSheet['skid_by_weight'] ?? false) === true) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * MISSING METHOD 7: Legacy calculation fallback
     */
    public static function calculateFreightRatesLegacy(array $totals, array $selectedCustomerData, array $formState): array
    {
        // Fallback implementation for when new engine is disabled
        return [
            'freight_rate' => 0,
            'fuel_surcharge' => 0,
            'taxes' => ['pst' => 0, 'gst' => 0]
        ];
    }
    /**
     * FINAL: Improved piece bracket with config fallback
     */
    private static function getPieceBracket(int $pieces, array $availableBrackets): string
    {
        $numericBrackets = array_filter($availableBrackets, function ($bracket) {
            return is_numeric($bracket) && $bracket > 0;
        });

        if (empty($numericBrackets)) {
            // Use config fallback instead of hardcoded '1'
            return config('ratesheet.default_skid_bracket', 'ltl');
        }

        sort($numericBrackets, SORT_NUMERIC);

        if ($pieces < min($numericBrackets)) {
            return (string) min($numericBrackets);
        }

        $selectedBracket = (string) min($numericBrackets);
        foreach ($numericBrackets as $bracket) {
            if ($pieces >= $bracket) {
                $selectedBracket = (string) $bracket;
            } else {
                break;
            }
        }

        return $selectedBracket;
    }

    private static function normalizeCityName(string $cityName): string
    {
        return strtoupper(trim($cityName));
    }

    /**
     * Calculate pieces from weight using customer's rule
     */
    public static function calculatePiecesFromWeight(float $weight, int $rule): int
    {
        if ($weight <= 0 || $rule <= 0) {
            return 0;
        }

        $pieces = $weight / $rule;

        // Round up if there's any fractional part
        return (fmod($pieces, 1) > 0) ? (int) $pieces + 1 : (int) $pieces;
    }

    /**
     * Calculate pieces from size dimensions
     */
    public static function calculatePiecesFromSize(int $pieces, float $length, float $width, float $height, bool $stackable): int
    {
        $newPieces = $pieces;

        // Round dimensions up for comparison
        $length = ceil($length);
        $width = ceil($width);
        $height = ceil($height);

        // Check if dimensions exceed limits
        if ($length > 48 || $width > 48 || $height > 82) {
            // Calculate additional pieces based on length
            $newPieces = ceil($length / 48) * $pieces;

            // Double if width or height exceed limits
            if ($width > 48 || $height > 82) {
                $newPieces *= 2;
            }
        }

        // Apply stackable multiplier
        if ($stackable) {
            $newPieces *= 2;
        }

        return $newPieces;
    }



    /**
     * Simple callback helper - just calculate with current form data
     */
    private static function recalculateAccessorialInCallback(bool $isIncluded, Set $set, Get $get): void
    {
        if (!$isIncluded) {
            $set('calculated_amount', 0);
            return;
        }

        try {
            // Build accessorial data from form fields
            $accessorial = [
                'accessorial_id' => $get('accessorial_id'),
                'qty' => $get('qty') ?: 1,
                'is_included' => $isIncluded,
                'rate' => $get('rate') ?: 0,
                'min' => $get('min') ?: 0,
                'max' => $get('max') ?: 0,
                'free_time' => $get('free_time') ?: 0,
                'base_amount' => $get('base_amount') ?: 0,
                'type' => $get('type') ?: 'fixed_price',
                'amount_type' => $get('amount_type') ?: 'Percentage',
                'time_unit' => $get('time_unit') ?: 'minute',
            ];

            // Get parent form state - if this fails, use minimal state
            try {
                $formState = $get('../../');
            } catch (Exception $e) {
                $formState = ['freight_rate_amount' => 0];
            }

            $amount = self::calculateAccessorialAmount($accessorial, $formState);
            $set('calculated_amount', $amount);
        } catch (Exception $e) {
            Log::error("Error in accessorial callback: " . $e->getMessage());
            $set('calculated_amount', 0);
        }
    }

    // ==========================================
    // COMMON ACCESSORIAL CALCULATION METHODS
    // Used by both CreateOrder and EditOrder
    // No unnecessary database calls - uses cached data
    // ==========================================

    /**
     * Calculate accessorial amount based on type and customer overrides
     * Uses Electron's calculation logic with Trocent's field names
     */
    public static function calculateAccessorialAmount(array $accessorial, array $formState, array $selectedCustomerData = []): float
    {
        try {
            $accessorialId = $accessorial['accessorial_id'] ?? null;
            $qty = (float) ($accessorial['qty'] ?? 0);
            $isIncluded = $accessorial['is_included'] ?? false;

            // If not included or no quantity, return 0
            if (!$isIncluded || $qty <= 0 || !$accessorialId) {
                return 0;
            }

            // Get accessorial type and settings
            $accessorialType = $accessorial['type'] ?? 'fixed_price';
            $rate = (float) ($accessorial['rate'] ?? 0);
            $min = (float) ($accessorial['min'] ?? 0);
            $max = (float) ($accessorial['max'] ?? 0);
            $freeTime = (float) ($accessorial['free_time'] ?? 0);
            $baseAmount = (float) ($accessorial['base_amount'] ?? 0);
            $amountType = $accessorial['amount_type'] ?? 'Percentage';
            $timeUnit = $accessorial['time_unit'] ?? 'minute';

            // Get freight rate from YOUR form structure
            $freightRate = (float) ($formState['freight_rate_amount'] ?? 0);

            $calculatedAmount = 0;

            // Apply Electron's calculation logic with YOUR data
            switch ($accessorialType) {
                case 'fixed_price':
                    $calculatedAmount = $rate * $qty;
                    break;

                case 'fuel_based':
                    if ($amountType === 'Percentage') {
                        $calculatedAmount = ($rate / 100) * $freightRate;
                    } else {
                        $calculatedAmount = $rate;
                    }
                    break;

                case 'transport_based':
                    if ($amountType === 'Percentage') {
                        $calculatedAmount = ($rate / 100) * $freightRate;
                    } else {
                        $calculatedAmount = $rate * $freightRate;
                    }
                    break;

                case 'time_based':
                    $calculatedAmount = self::calculateTimeBasedAccessorial(
                        $formState,
                        $rate,
                        $baseAmount,
                        $freeTime,
                        $timeUnit
                    );
                    break;

                default:
                    $calculatedAmount = $rate * $qty;
                    break;
            }

            // Apply quantity multiplier for non-time-based accessorials
            if ($accessorialType !== 'time_based') {
                $calculatedAmount *= $qty;
            }

            // Apply min/max constraints (Electron's logic)
            if ($min > 0 && $calculatedAmount < $min) {
                $calculatedAmount = $min;
            }
            if ($max > 0 && $calculatedAmount > $max) {
                $calculatedAmount = $max;
            }

            return round($calculatedAmount, 2);
        } catch (Exception $e) {
            Log::error("Error calculating accessorial amount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate time-based accessorial using Electron's logic with YOUR time fields
     */
    public static function calculateTimeBasedAccessorial(array $formState, float $rate, float $baseAmount, float $freeTime, string $timeUnit): float
    {
        // Use YOUR time field names from YOUR form
        $pickupInTime = $formState['pickup_in_time'] ?? null;
        $pickupOutTime = $formState['pickup_out_time'] ?? null;
        $deliveryInTime = $formState['delivery_in_time'] ?? null;
        $deliveryOutTime = $formState['delivery_out_time'] ?? null;

        // Calculate waiting times using Electron's logic
        $totalPickupWaitingTime = self::calculateTimeDifferenceInMinutes($pickupInTime, $pickupOutTime);
        $totalDeliveryWaitingTime = self::calculateTimeDifferenceInMinutes($deliveryInTime, $deliveryOutTime);

        // Check address exclusions using YOUR address field names
        $shipperName = $formState['shipper_name'] ?? '';
        $receiverName = $formState['receiver_name'] ?? '';

        $excludePickup = false;
        $excludeDelivery = false;

        try {
            if ($shipperName) {
                $excludePickup = AddressBook::where('name', $shipperName)->where('no_waiting_time', 1)->exists();
            }
            if ($receiverName) {
                $excludeDelivery = AddressBook::where('name', $receiverName)->where('no_waiting_time', 1)->exists();
            }
        } catch (Exception $e) {
            // If AddressBook query fails, just continue without exclusions
        }

        // Apply exclusions
        if ($excludePickup) {
            $totalPickupWaitingTime = 0;
        }
        if ($excludeDelivery) {
            $totalDeliveryWaitingTime = 0;
        }

        // Convert free time to minutes (Electron's logic)
        $freeTimeMinutes = ($timeUnit === 'hour') ? $freeTime * 60 : $freeTime;

        // Apply Electron's calculation logic
        $billablePickupTime = max(0, $totalPickupWaitingTime - $freeTimeMinutes);
        $billableDeliveryTime = max(0, $totalDeliveryWaitingTime - $freeTimeMinutes);
        $totalBillableTime = $billablePickupTime + $billableDeliveryTime;

        return $baseAmount + ($rate * $totalBillableTime);
    }

    /**
     * Calculate time difference in minutes (Electron's helper function)
     */
    public static function calculateTimeDifferenceInMinutes(?string $startTime, ?string $endTime): int
    {
        if (!$startTime || !$endTime || $startTime === '00:00:00' || $endTime === '00:00:00') {
            return 0;
        }

        try {
            $start = Carbon::parse($startTime);
            $end = Carbon::parse($endTime);
            return $end->diffInMinutes($start);
        } catch (Exception $e) {
            Log::error("Error calculating time difference: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Complete accessorial charges calculation with all 4 types
     * Uses already loaded data - no additional DB calls
     */
    public static function calculateAccessorialCharges(array $formState): array
    {
        $accessorials = $formState['customer_accessorials'] ?? [];
        $otherServiceCharges = $formState['service_charges'];
        $totalAccessorialCharges = 0;
        $fuelBasedAccessorialCharges = 0;

        foreach ($accessorials as $accessorial) {
            if (!($accessorial['is_included'] ?? false)) continue;

            // Check if this is fuel-based for fuel surcharge tracking (use loaded type)
            $accessorialType = $accessorial['type'] ?? 'fixed_price';
            if ($accessorialType === 'fuel_based') {
                $fuelBasedAccessorialCharges += $accessorial["calculated_amount"];
            }

            $totalAccessorialCharges += $accessorial["calculated_amount"];
        }

        if (!empty($otherServiceCharges))
        {
            foreach ($otherServiceCharges as $otherServiceCharge) {
                $totalAccessorialCharges += (float) ($otherServiceCharge["charge_amount"]);
            }
        }

        return [
            'total_accessorial_charges' => $totalAccessorialCharges,
            'fuel_based_accessorial_charges' => $fuelBasedAccessorialCharges
        ];
    }

    /**
     * Common form field update callbacks for accessorials
     * Returns the callback functions that can be used in both Create and Edit forms
     */
    public static function getAccessorialFormCallbacks(): array
    {
        return [
            // Smart toggle callback - auto-check when qty > 0
            'toggleCallback' => function ($state, Set $set, Get $get) {
                if ($state) {
                    // Toggle checked - set default qty if needed
                    if ((int)$get('qty') === 0) {
                        $set('qty', 1);
                    }
                }

                // Always recalculate when toggle changes
                self::recalculateAccessorialInCallback($state, $set, $get);
            },

            // Smart quantity callback - auto-check toggle when qty > 0
            'qtyCallback' => function ($state, Set $set, Get $get) {
                $qty = (int)$state;

                if ($qty > 0) {
                    // Auto-check toggle if user enters quantity
                    if (!$get('is_included')) {
                        $set('is_included', true);
                    }
                } else {
                    // Auto-uncheck toggle if qty is 0
                    $set('is_included', false);
                }

                // Always recalculate when quantity changes
                self::recalculateAccessorialInCallback($qty > 0, $set, $get);
            },

        ];
    }

    // ==========================================
    // EXISTING CALCULATION METHODS (Enhanced)
    // ==========================================

    /**
     * Enhanced service charges calculation
     */
    public static function calculateServiceCharges(array $formState, array $selectedCustomerData, float $currentFreightRate = 0): array
    {
        if($formState["no_charges"])
        {
            return [
                'rush_service_charge' => 0,
                'direct_service_charge' => 0,
                'total_service_charges' => 0
            ];
        }

        $serviceType = $formState['service_type'] ?? 'standard';
        $serviceKm = (float) ($formState['direct_km'] ?? 0);
        $vehicleTypes = $formState['customer_vehicle_types'] ?? [];
        $customer = $selectedCustomerData['customer'];

        $rushServiceCharge = 0;
        $directServiceCharge = 0;

        // Rush Service Charge Calculation - ELECTRON WAY
        if ($serviceType === 'rush') {
            $rushPercentage = (float) ($customer->rush_service_charge ?? 0);
            $rushMinimum = (float) ($customer->rush_service_charge_min ?? 0);

            $rushServiceCharge = ($rushPercentage / 100) * $currentFreightRate;

            // Apply minimum if specified
            if ($rushMinimum > 0 && $rushServiceCharge < $rushMinimum) {
                $rushServiceCharge = $rushMinimum;
            }
        }

        // Direct Service Charge Calculation
        if ($serviceType === 'direct') {
            $customerVehicleCharges = $nonCustomerVehicleCharges = 0;
            $customer_has_vehicle = false;
            foreach ($vehicleTypes as $vehicleType) {
                if ($vehicleType['is_selected']) {
                    $rate = (float) ($vehicleType['vehicle_rate'] ?? 0);
                    if (!empty($vehicleType["customer_id"]))
                    {
                        $customerVehicleCharges += $rate * $serviceKm;
                        $customer_has_vehicle = true;
                    }
                    else
                    {
                        $nonCustomerVehicleCharges += $rate * $serviceKm;
                    }
                }
            }
            $directServiceCharge = $customer_has_vehicle ? $customerVehicleCharges : $nonCustomerVehicleCharges;
        }

        return [
            'rush_service_charge' => $rushServiceCharge,
            'direct_service_charge' => $directServiceCharge,
            'total_service_charges' => $rushServiceCharge + $directServiceCharge
        ];
    }

    /**
     * Enhanced fuel surcharge calculation
     */
    public static function calculateFuelSurcharge(float $freightRate, float $weight, array $selectedCustomerData, $formState): float
    {
        if($formState["no_charges"]) return 0;
        if ($formState["manual_fuel_surcharges"]) return $formState['fuel_surcharge_amount'];

        $order_date = $formState['created_at'] ?? date('Y-m-d');
        $customer = $selectedCustomerData['customer'];
        $fuelSurchargeRule = (float) ($customer->fuel_surcharge_rule ?? 10000);

        $customer_fuel_surcharge = FuelSurcharge::where(function ($query) use ($order_date) {
            $query->where('from_date', '<=', $order_date)
                ->where('to_date', '>=', $order_date);
            })->orWhere(function ($query) {
                $query->whereRaw('1 = 1');
            })
            ->orderBy('id', 'DESC')
            ->first();

        // LTL vs FTL determination like Electron
        if ($weight < $fuelSurchargeRule) {
            // Use LTL fuel surcharge
            if ($customer->fuel_surcharges_other == 1) {
                $fuelPercentage = (float) ($customer->fuel_surcharges / 100) * $customer->fuel_surcharges_other_value;
            } else {
                $fuelPercentage = (float) ($customer->fuel_surcharges / 100) * $customer_fuel_surcharge->ltl_surcharge;
            }
        } else {
            // Use FTL fuel surcharge
            if ($customer->fuel_surcharges_other_ftl == 1) {
                $fuelPercentage = (float) ($customer->fuel_surcharges_ftl / 100) * $customer->fuel_surcharges_other_value_ftl;
            } else {
                $fuelPercentage = (float) ($customer->fuel_surcharges_ftl / 100) * $customer_fuel_surcharge->ftl_surcharge;
            }
        }

        return ($fuelPercentage / 100) * $freightRate;
    }

    /**
     * Enhanced provincial tax calculation
     */
    public static function calculateProvincialTaxes(float $freightRate, string $province, array $selectedCustomerData): array
    {
        $customer = $selectedCustomerData['customer'];

        // Check if customer is tax exempt
        if ($customer->no_tax ?? false) {
            return ['pst' => 0, 'gst' => 0];
        }

        // Tax rates by province (you may want to move this to config)
        $taxRates = [
            'ON' => ['pst' => 8, 'gst' => 5],
            'BC' => ['pst' => 7, 'gst' => 5],
            'AB' => ['pst' => 0, 'gst' => 5],
            'SK' => ['pst' => 6, 'gst' => 5],
            'MB' => ['pst' => 7, 'gst' => 5],
            'QC' => ['pst' => 9.975, 'gst' => 5],
            'NB' => ['pst' => 10, 'gst' => 5],
            'NS' => ['pst' => 10, 'gst' => 5],
            'PE' => ['pst' => 10, 'gst' => 5],
            'NL' => ['pst' => 10, 'gst' => 5],
            'YT' => ['pst' => 0, 'gst' => 5],
            'NT' => ['pst' => 0, 'gst' => 5],
            'NU' => ['pst' => 0, 'gst' => 5],
        ];

        $rates = $taxRates[$province] ?? ['pst' => 0, 'gst' => 0];

        return [
            'pst' => ($rates['pst'] / 100) * $freightRate,
            'gst' => ($rates['gst'] / 100) * $freightRate,
        ];
    }
}
