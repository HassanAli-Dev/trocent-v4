<?php

namespace App\Filament\Resources\OrderResource\Pages;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Customer;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Carbon\Carbon;
use Exception;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Accessorial;
// CustomerAccessorialCharge is now a pivot relationship
use App\Models\VehicleType;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Actions;
use App\Models\AddressBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\UnitConversionService;





class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
    protected static ?string $title = 'Create New Order';
    protected string $view = 'filament.resources.order-resource.pages.create-order';

    public $orderNumber = null;
    public $selectedCustomerData = null;
    public $selectedCustomerId = null;

    // FIX: Add missing Livewire properties
    public $totalActualWeight = '0.00 lbs';
    public $totalVolumeWeight = '0.00 lbs';
    public $totalChargeableWeight = '0.00 lbs';
    public $totalChargeablePieces = 0;
    public $totalPieces = 0; // FIX: Added missing property
    public $weightInKg = '0.00 kg';

    public $freightRateAmount = '$0.00';
    public $fuelSurchargeAmount = '$0.00';
    public $provincialTaxAmount = '$0.00';
    public $federalTaxAmount = '$0.00';
    public $subTotalAmount = '$0.00';
    public $grandTotalAmount = '$0.00';
    public $tempFreightData = [];


    public function mount(): void
    {
        parent::mount();
        $this->orderNumber = $this->generateOrderNumber();
        $this->resetDisplaysToZero();
    }

    protected function resetDisplaysToZero(): void
    {
        $this->totalActualWeight = '0.00 lbs';
        $this->totalVolumeWeight = '0.00 lbs';
        $this->totalChargeableWeight = '0.00 lbs';
        $this->totalChargeablePieces = 0;
        $this->totalPieces = 0; // FIX: Added missing reset
        $this->weightInKg = '0.00 kg';

        $this->freightRateAmount = '$0.00';
        $this->fuelSurchargeAmount = '$0.00';
        $this->provincialTaxAmount = '$0.00';
        $this->federalTaxAmount = '$0.00';
        $this->subTotalAmount = '$0.00';
        $this->grandTotalAmount = '$0.00';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // BASIC INFO, CLIENT INFO, REFERENCES - 3 SECTIONS
            Grid::make(3)
                ->extraAttributes(['class' => 'auto-rows-fr gap-x-4'])
                ->schema([

                    // === BASIC INFORMATION SECTION ===
                    Section::make('Basic Information')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            // Row 1: Username and Order Number
                            Grid::make(2)->schema([
                                TextInput::make('username')
                                    ->label('Username')
                                    ->default(Auth::user()->name)
                                    ->disabled()
                                    ->columnSpan(1),
                                TextInput::make('order_code')
                                    ->label('Order Number')
                                    ->default($this->orderNumber)
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),
                            ]),

                            // Row 2: Create Date and Terminal
                            Grid::make(2)->schema([
                                DatePicker::make('created_at')
                                    ->label('Create Date')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(1),
                                Select::make('terminal_id')
                                    ->label('Terminal')
                                    ->options(config('terminals.terminals'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Auto-set pickup and delivery terminals to same value
                                        $set('pickup_terminal_id', $state);
                                        $set('delivery_terminal_id', $state);
                                    })
                                    ->columnSpan(1),
                            ]),

                            // Row 3: Quote and Is Crossdock
                            Grid::make(2)->schema([
                                Toggle::make('is_quote')
                                    ->label('Quote')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        if ($state) {
                                            // When quote is active
                                            $set('status', 'quote');
                                            $set('order_type', 1); // Order Entry
                                            $set('is_crossdock', false); // Deactivate crossdock
                                        }
                                    })
                                    ->columnSpan(1), // REMOVED ->disabled() - now always clickable
                                Toggle::make('is_crossdock')
                                    ->label('Is Crossdock')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        if ($state) {
                                            // When crossdock is active
                                            $set('status', 'approved');
                                            $set('order_type', 2); // Order Billing
                                            $set('is_quote', false); // Deactivate quote
                                        }
                                    })
                                    ->columnSpan(1),
                            ]),

                            // Row 4: Order Type and Order Status
                            Grid::make(2)->schema([
                                Select::make('order_type')
                                    ->label('Order Type')
                                    ->options(config('terminals.order_types'))
                                    ->default(config('terminals.default_order_type'))
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        if ($state == 2) { // Order Billing
                                            // When Order Type is Billing - same logic as crossdock
                                            $set('is_quote', false);
                                            $set('status', 'approved');
                                            // Optional: could also set crossdock if that makes sense for your business
                                            // $set('is_crossdock', true);
                                        }
                                    })
                                    ->columnSpan(1),
                                Select::make('status')
                                    ->label('Order Status')
                                    ->options(config('terminals.order_statuses'))
                                    ->default(config('terminals.default_status'))
                                    ->required()
                                    ->live()
                                    ->columnSpan(1),
                            ]),

                            // Internal Notes - Full Width
                            Textarea::make('internal_notes')
                                ->label('Internal Notes')
                                ->rows(3),
                        ]),


                    // === CLIENT INFORMATION SECTION ===
                    Section::make('Client Information')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            Select::make('customer_id')
                                ->label('Customer')
                                ->relationship('customer', 'account_number', function ($query) {
                                    return $query->selectRaw("id, CONCAT(account_number, ' - ', full_name) as account_number, full_name");
                                })
                                ->getOptionLabelFromRecordUsing(function ($record) {
                                    return $record->account_number . ' - ' . $record->full_name;
                                })
                                ->searchable(['account_number', 'full_name'])
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) {
                                        // Load complete customer data with relationships
                                        $customer = Customer::find($state);
                                        if ($customer) {
                                            $set('client_name', $customer->full_name);
                                            $billingEmails = $customer->billing_email;
                                            $firstEmail = '';
                                            if (is_array($billingEmails) && !empty($billingEmails)) {
                                                $firstEmail = $billingEmails[0];
                                            }
                                            $set('client_email', $firstEmail);
                                            $set('client_address', $customer->address ?? '');
                                            $set('client_suite', $customer->suite ?? '');
                                            $set('client_city', $customer->city ?? '');
                                            $set('client_province', $customer->province ?? '');
                                            $set('client_postal_code', $customer->postal_code ?? '');

                                            // ← Build enhanced cache
                                            $enhancedRateSheets = $customer->buildEnhancedRateSheetCache();

                                            // ✅ CHANGED: Store rate sheets in Laravel cache instead of component
                                            Cache::put("customer_rates:{$state}", $enhancedRateSheets, 3600); // 1 hour cache

                                            $accessorials = Cache::remember("customer_accessorials:{$state}", 3600, function () use ($customer) {
                                                return $customer->accessorials()->get()->toArray();
                                            });

                                            $vehicleTypes = Cache::remember("customer_vehicle_types:{$state}", 3600, function () use ($customer) {
                                                return $customer->vehicleTypes()->get()->toArray();
                                            });
                                            // ✅ CHANGED: Store only lightweight data in component
                                            $this->selectedCustomerId = $state; // Store customer ID
                                            $this->selectedCustomerData = [
                                                'customer' => $customer,
                                                // ✅ REMOVED: Don't store rate_sheets in component anymore
                                                'accessorials' => $accessorials,
                                                'vehicle_types' => $vehicleTypes,
                                                'billing_settings' => [
                                                    'weight_to_pieces_rule' => (int) ($customer->weight_to_pieces_rule ?? 1000),
                                                    'fuel_surcharge_rule' => (float) ($customer->fuel_surcharge_rule ?? 10000),
                                                    'fuel_surcharges' => $customer->fuel_surcharges ?? false,
                                                    'fuel_surcharges_other' => $customer->fuel_surcharges_other ?? false,
                                                    'fuel_surcharges_other_value' => (float) ($customer->fuel_surcharges_other_value ?? 0),
                                                    'fuel_surcharges_ftl' => $customer->fuel_surcharges_ftl ?? false,
                                                    'fuel_surcharges_other_ftl' => $customer->fuel_surcharges_other_ftl ?? false,
                                                    'fuel_surcharges_other_value_ftl' => (float) ($customer->fuel_surcharges_other_value_ftl ?? 0),
                                                    'no_tax' => $customer->no_tax ?? false,
                                                ]
                                            ];

                                            // Call loadCustomerData AFTER storing the cache
                                            $this->loadCustomerData($state, $set);

                                            // ← Show enhanced cache info in notification
                                            $rateSheetCount = $customer->rateSheets->count();
                                            $hasSkidByWeight = !empty($enhancedRateSheets['skid2']['available_brackets']);

                                            $message = "Loaded {$rateSheetCount} rate sheets and customer settings";
                                            if ($hasSkidByWeight) {
                                                $message .= " (includes skid-by-weight rates)";
                                            }

                                            Notification::make()
                                                ->title('Customer Data Loaded')
                                                ->body($message)
                                                ->success()
                                                ->duration(3000)
                                                ->send();
                                        }
                                    }
                                }),

                            Grid::make(2)->schema([
                                TextInput::make('client_name')
                                    ->label('Name')
                                    ->required(),
                                TextInput::make('client_email')
                                    ->label('Email')
                                    ->email(),
                            ]),

                            TextInput::make('client_address')
                                ->label('Address'),

                            Grid::make(2)->schema([
                                TextInput::make('client_suite')
                                    ->label('Suite'),
                                TextInput::make('client_city')
                                    ->label('City'),
                            ]),

                            Grid::make(2)->schema([
                                TextInput::make('client_province')
                                    ->label('Province/State'),
                                TextInput::make('client_postal_code')
                                    ->label('Postal Code'),
                            ]),
                        ]),

                    Section::make('References')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            // Reference Numbers with TagsInput
                            TagsInput::make('reference_numbers')
                                ->label('Reference Numbers')
                                ->placeholder('Type and press Enter to add')
                                ->separator(',')
                                ->splitKeys(['Enter', ',', ' ']) // Use space instead of Tab
                                ->helperText('Add multiple reference numbers (use Enter, comma, or space)'),

                            // Caller (full width)
                            TextInput::make('caller')
                                ->label('Caller'),

                            // Spacer to fill remaining space
                            Group::make([])->extraAttributes(['class' => 'flex-grow']),
                        ]),
                ]),



            // SHIPPER, EXTRA STOP, RECEIVER - 3 SECTIONS
            Grid::make(3)
                ->extraAttributes(['class' => 'auto-rows-fr gap-x-4'])
                ->schema([
                    Section::make('Shipper Details')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            Grid::make(2)->schema([
                                OrderResource::createAddressBookSearchableInput('shipper_name', 'shipper', true)
                                    ->columnSpan(1),

                                TextInput::make('shipper_email')
                                    ->label('Email')
                                    ->email()
                                    ->columnSpan(1),
                            ]),

                            Grid::make(2)->schema([
                                TextInput::make('shipper_contact_name')
                                    ->label('Contact Name'),
                                TextInput::make('shipper_phone')
                                    ->label('Phone Number'),
                            ]),

                            TextInput::make('shipper_address')
                                ->label('Address')
                                ->required(),

                            Grid::make(2)->schema([
                                TextInput::make('shipper_suite')
                                    ->label('Suite'),
                                TextInput::make('shipper_city')
                                    ->label('City')
                                    ->required(),
                            ]),

                            Grid::make(2)->schema([
                                TextInput::make('shipper_province')
                                    ->label('Province/State')
                                    ->required(),
                                TextInput::make('shipper_postal_code')
                                    ->label('Postal Code')
                                    ->required(),
                            ]),

                            Textarea::make('shipper_special_instructions')
                                ->label('Special Instructions')
                                ->rows(2),
                        ]),


                    Section::make('Extra Stop')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            Toggle::make('extra_stop_toggle')
                                ->label('Extra Stop')
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state) {
                                        // When toggle is turned ON, set default name and trigger lookup
                                        $set('crossdock_name', 'MESSAGERS');

                                        // Manually trigger the address book lookup
                                        $addressBook = AddressBook::where('name', 'MESSAGERS')->first();

                                        if ($addressBook) {
                                            OrderResource::populateAddressBookFields($set, 'crossdock', $addressBook);
                                        }
                                    } else {
                                        // When toggle is turned OFF, clear all crossdock fields
                                        $set('crossdock_name', null);
                                        OrderResource::clearAddressBookFields($set, 'crossdock');
                                    }
                                }),

                            Group::make([
                                Grid::make(2)->schema([
                                    OrderResource::createAddressBookSearchableInput('crossdock_name', 'crossdock', false)
                                        ->columnSpan(1),
                                    TextInput::make('crossdock_email')
                                        ->label('Email'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('crossdock_contact_name')
                                        ->label('Contact Name'),
                                    TextInput::make('crossdock_phone')
                                        ->label('Phone Number'),
                                ]),
                                TextInput::make('crossdock_address')
                                    ->label('Address'),
                                Grid::make(2)->schema([
                                    TextInput::make('crossdock_suite')
                                        ->label('Suite'),
                                    TextInput::make('crossdock_city')
                                        ->label('City'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('crossdock_province')
                                        ->label('Province/State'),
                                    TextInput::make('crossdock_postal_code')
                                        ->label('Postal Code'),
                                ]),
                            ])->visible(fn(Get $get) => $get('extra_stop_toggle')),
                        ]),

                    Section::make('Receiver Details')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            Grid::make(2)->schema([
                                OrderResource::createAddressBookSearchableInput('receiver_name', 'receiver', true)

                                    ->columnSpan(1),
                                TextInput::make('receiver_email')
                                    ->label('Email')
                                    ->email(),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('receiver_contact_name')
                                    ->label('Contact Name'),
                                TextInput::make('receiver_phone')
                                    ->label('Phone Number'),
                            ]),
                            TextInput::make('receiver_address')
                                ->label('Address')
                                ->required(),
                            Grid::make(2)->schema([
                                TextInput::make('receiver_suite')
                                    ->label('Suite'),
                                TextInput::make('receiver_city')
                                    ->label('City')
                                    ->required(),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('receiver_province')
                                    ->label('Province/State')
                                    ->required(),
                                TextInput::make('receiver_postal_code')
                                    ->label('Postal Code')
                                    ->required(),
                            ]),
                            Textarea::make('receiver_special_instructions')
                                ->label('Special Instructions')
                                ->rows(2),
                        ]),
                ]),


            // PICKUP, INTERLINE, DELIVERY - 3 SECTIONS
            Grid::make(3)
                ->extraAttributes(['class' => 'auto-rows-fr gap-x-4'])->schema([
                    // === PICKUP DETAILS SECTION ===
                    Section::make('Pickup Details')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            DatePicker::make('pickup_date')
                                ->label('Pickup Date')
                                ->required(),
                            Grid::make(2)->schema([
                                TimePicker::make('pickup_time_from')
                                    ->label('Time From'),
                                TimePicker::make('pickup_time_to')
                                    ->label('Time To'),
                            ]),
                            TextInput::make('pickup_driver_assigned')
                                ->label('Driver Assigned'),
                            Select::make('pickup_terminal_id')
                                ->label('Pickup Terminal')
                                ->options(config('terminals.terminals'))
                                ->searchable(),
                            Toggle::make('pickup_appointment')
                                ->label('Appointment')
                                ->live()
                                ->reactive(),
                            TagsInput::make('pickup_appointment_numbers')
                                ->label('Appointment Numbers')
                                ->visible(fn(Get $get) => $get('pickup_appointment'))
                                ->placeholder('Type and press Enter to add')
                                ->separator(',')
                                ->splitKeys(['Tab', 'Enter', ','])
                                ->helperText('Add multiple appointment numbers'),
                        ]),

                    Section::make('Interline Carrier')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            // Top level toggles - Pickup and Delivery only
                            Grid::make(2)->schema([
                                Toggle::make('interline_pickup')
                                    ->label('Pickup')
                                    ->live(),
                                Toggle::make('interline_delivery')
                                    ->label('Delivery')
                                    ->live(),
                            ]),

                            // Same Carrier toggle - appears below when both services are selected
                            Toggle::make('same_interline_carrier')
                                ->label('Same Carrier for Both')
                                ->default(true) // Default to same carrier
                                ->live()
                                ->visible(fn(Get $get) => $get('interline_pickup') && $get('interline_delivery'))
                                ->helperText('Uncheck to use different carriers for pickup and delivery'),

                            // Single carrier section (when same carrier is selected OR only one service is selected)
                            Group::make([
                                Grid::make(2)->schema([
                                    // Use the new reusable delivery agent search input
                                    OrderResource::createDeliveryAgentSearchableInput('interline_name', 'interline', true)
                                        ->columnSpan(1),

                                    TextInput::make('interline_email')
                                        ->label('Email')
                                        ->email()
                                        ->columnSpan(1),
                                ]),

                                // Hidden field to store the delivery agent ID
                                Hidden::make('interline_id'),

                                Grid::make(2)->schema([
                                    TextInput::make('interline_contact_name')
                                        ->label('Contact Name')
                                        ->columnSpan(1),
                                    TextInput::make('interline_phone')
                                        ->label('Phone Number')
                                        ->columnSpan(1),
                                ]),
                                TextInput::make('interline_address')
                                    ->label('Address'),
                                Grid::make(2)->schema([
                                    TextInput::make('interline_suite')
                                        ->label('Suite')
                                        ->columnSpan(1),
                                    TextInput::make('interline_city')
                                        ->label('City')
                                        ->columnSpan(1),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('interline_province')
                                        ->label('Province/State')
                                        ->columnSpan(1),
                                    TextInput::make('interline_postal_code')
                                        ->label('Postal Code')
                                        ->columnSpan(1),
                                ]),
                                Textarea::make('interline_special_instructions')
                                    ->label('Special Instructions')
                                    ->rows(2),

                                Fieldset::make('Charges')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('interline_charge_amount')
                                                ->label('Total Charge Amount')
                                                ->numeric()
                                                ->prefix('$')
                                                ->columnSpan(1),
                                            TextInput::make('interline_reference')
                                                ->label('Reference/Invoice #')
                                                ->columnSpan(1),
                                        ]),
                                    ]),
                            ])
                                ->visible(function (Get $get) {
                                    $pickup = $get('interline_pickup');
                                    $delivery = $get('interline_delivery');
                                    $sameCarrier = $get('same_interline_carrier') ?? true;

                                    return ($pickup && $delivery && $sameCarrier) ||
                                        ($pickup && !$delivery) ||
                                        (!$pickup && $delivery);
                                }),

                            // Tabs for different carriers (when both services selected but DIFFERENT carriers)
                            Tabs::make('InterlineCarriers')
                                ->tabs([
                                    Tab::make('Pickup Carrier')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                OrderResource::createDeliveryAgentSearchableInput('pickup_interline_name', 'pickup_interline', true)
                                                    ->columnSpan(1),
                                                TextInput::make('pickup_interline_email')
                                                    ->label('Email')
                                                    ->email()
                                                    ->columnSpan(1),
                                            ]),

                                            Hidden::make('pickup_interline_id'),

                                            Grid::make(2)->schema([
                                                TextInput::make('pickup_interline_contact_name')
                                                    ->label('Contact Name')
                                                    ->columnSpan(1),
                                                TextInput::make('pickup_interline_phone')
                                                    ->label('Phone Number')
                                                    ->columnSpan(1),
                                            ]),
                                            TextInput::make('pickup_interline_address')
                                                ->label('Address'),
                                            Grid::make(2)->schema([
                                                TextInput::make('pickup_interline_suite')
                                                    ->label('Suite')
                                                    ->columnSpan(1),
                                                TextInput::make('pickup_interline_city')
                                                    ->label('City')
                                                    ->columnSpan(1),
                                            ]),
                                            Grid::make(2)->schema([
                                                TextInput::make('pickup_interline_province')
                                                    ->label('Province/State')
                                                    ->columnSpan(1),
                                                TextInput::make('pickup_interline_postal_code')
                                                    ->label('Postal Code')
                                                    ->columnSpan(1),
                                            ]),
                                            Textarea::make('pickup_interline_special_instructions')
                                                ->label('Special Instructions')
                                                ->rows(2),

                                            Fieldset::make('Pickup Charges')
                                                ->schema([
                                                    Grid::make(2)->schema([
                                                        TextInput::make('pickup_interline_charge_amount')
                                                            ->label('Charge Amount')
                                                            ->numeric()
                                                            ->prefix('$')
                                                            ->columnSpan(1),
                                                        TextInput::make('pickup_interline_reference')
                                                            ->label('Reference/Invoice #')
                                                            ->columnSpan(1),
                                                    ]),
                                                ]),
                                        ]),

                                    Tab::make('Delivery Carrier')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                OrderResource::createDeliveryAgentSearchableInput('delivery_interline_name', 'delivery_interline', true)
                                                    ->columnSpan(1),
                                                TextInput::make('delivery_interline_email')
                                                    ->label('Email')
                                                    ->email()
                                                    ->columnSpan(1),
                                            ]),

                                            Hidden::make('delivery_interline_id'),

                                            Grid::make(2)->schema([
                                                TextInput::make('delivery_interline_contact_name')
                                                    ->label('Contact Name')
                                                    ->columnSpan(1),
                                                TextInput::make('delivery_interline_phone')
                                                    ->label('Phone Number')
                                                    ->columnSpan(1),
                                            ]),
                                            TextInput::make('delivery_interline_address')
                                                ->label('Address'),
                                            Grid::make(2)->schema([
                                                TextInput::make('delivery_interline_suite')
                                                    ->label('Suite')
                                                    ->columnSpan(1),
                                                TextInput::make('delivery_interline_city')
                                                    ->label('City')
                                                    ->columnSpan(1),
                                            ]),
                                            Grid::make(2)->schema([
                                                TextInput::make('delivery_interline_province')
                                                    ->label('Province/State')
                                                    ->columnSpan(1),
                                                TextInput::make('delivery_interline_postal_code')
                                                    ->label('Postal Code')
                                                    ->columnSpan(1),
                                            ]),
                                            Textarea::make('delivery_interline_special_instructions')
                                                ->label('Special Instructions')
                                                ->rows(2),

                                            Fieldset::make('Delivery Charges')
                                                ->schema([
                                                    Grid::make(2)->schema([
                                                        TextInput::make('delivery_interline_charge_amount')
                                                            ->label('Charge Amount')
                                                            ->numeric()
                                                            ->prefix('$')
                                                            ->columnSpan(1),
                                                        TextInput::make('delivery_interline_reference')
                                                            ->label('Reference/Invoice #')
                                                            ->columnSpan(1),
                                                    ]),
                                                ]),
                                        ]),
                                ])
                                ->visible(function (Get $get) {
                                    $pickup = $get('interline_pickup');
                                    $delivery = $get('interline_delivery');
                                    $sameCarrier = $get('same_interline_carrier') ?? true;

                                    return $pickup && $delivery && !$sameCarrier;
                                }),

                            // Helper text when no services selected
                            Group::make([
                                Placeholder::make('interline_help')
                                    ->label('Interline Information')
                                    ->content('Enable pickup and/or delivery toggles above to configure interline carrier details.'),
                            ])->visible(fn(Get $get) => !$get('interline_pickup') && !$get('interline_delivery')),
                        ]),

                    // === DELIVERY DETAILS SECTION ===
                    Section::make('Delivery Details')
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->columnSpan(1)
                        ->schema([
                            DatePicker::make('delivery_date')
                                ->label('Delivery Date')
                                ->required(),
                            Grid::make(2)->schema([
                                TimePicker::make('delivery_time_from')
                                    ->label('Time From'),
                                TimePicker::make('delivery_time_to')
                                    ->label('Time To'),
                            ]),
                            TextInput::make('delivery_driver_assigned')
                                ->label('Driver Assigned'),
                            Select::make('delivery_terminal_id')
                                ->label('Delivery Terminal')
                                ->options(config('terminals.terminals'))
                                ->searchable(),
                            Toggle::make('delivery_appointment')
                                ->label('Appointment')
                                ->live(),
                            TagsInput::make('delivery_appointment_numbers')
                                ->label('Appointment Numbers')
                                ->visible(fn(Get $get) => $get('delivery_appointment'))
                                ->placeholder('Type and press Enter to add')
                                ->separator(',')
                                ->splitKeys(['Tab', 'Enter', ','])
                                ->helperText('Add multiple appointment numbers separated by commas'),
                        ]),
                ]),










            // FREIGHT DETAILS SECTION

            Section::make('Freight Details')
                ->extraAttributes(['class' => 'section-yellow-border'])
                ->schema([
                    Grid::make(4)->schema([
                        Select::make('service_type')
                            ->label('Service Type')
                            ->options([
                                'regular' => 'Regular',
                                'direct' => 'Direct',
                                'rush' => 'Rush',
                            ])
                            ->default('regular')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if ($state !== 'direct') {
                                    $set('direct_km', '');
                                    $vehicleTypes = $get('customer_vehicle_types') ?: [];
                                    foreach ($vehicleTypes as $index => $vehicle) {
                                        $set("customer_vehicle_types.{$index}.is_selected", false);
                                    }
                                }
                                $this->updateOrderTotals();
                            })
                            ->columnSpan(1),
                    ]),

                    Repeater::make('freights')
                        ->key("freight")
                        ->live()
                        ->schema([
                            Grid::make(10)->schema([
                                Select::make('freight_type')
                                    ->label('Type')
                                    ->required()
                                    ->placeholder("Select Type")
                                    ->options([
                                        'skid' => 'Skid',
                                        'box' => 'Box',
                                        'envelope' => 'Envelope'
                                    ])
                                    ->native(false)
                                    ->default('skid')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals(); // Trigger order-level calculations
                                    })
                                    ->columnSpan(1),

                                TextInput::make('freight_description')
                                    ->label('Description')
                                    ->default('FAK')
                                    ->columnSpan(1),

                                TextInput::make('freight_pieces')
                                    ->label('Pieces')
                                    ->numeric()
                                    ->default(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals(); // Trigger order-level calculations
                                    })
                                    ->columnSpan(1),

                                TextInput::make('freight_weight')
                                    ->label('Weight')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->helperText(function (Get $get) {
                                        $chargeableWeight = (float) ($get('freight_chargeable_weight') ?? 0);
                                        return $chargeableWeight > 0
                                            ? 'Vol: ' . number_format($chargeableWeight, 2) . ' lbs'
                                            : 'Vol: 0.00 lbs';
                                    })
//                                    ->partiallyRenderComponentsAfterStateUpdated(['../../../freight_and_other_charges.freight_charges.freight_rate_amount'])
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
//                                        dd($get("../../freight_rate_amount"));
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals(); // Trigger order-level calculations
                                    })
                                    ->columnSpan(1),

                                Select::make('weight_unit')
                                    ->label('Unit')
                                    ->options(['lbs' => 'LBS', 'kg' => 'KG'])
                                    ->default('lbs')
                                    ->live()
                                    ->helperText(function (Get $get) {
                                        $hasConversion = $get('has_unit_conversion') ?? false;
                                        return $hasConversion ? 'Conv.: ON' : 'Conv.: OFF';
                                    })
                                    ->suffixAction(
                                        Action::make('toggleConversion')
                                            ->icon('heroicon-m-arrow-path')
                                            ->color(fn(Get $get): string => ($get('has_unit_conversion') ?? false) ? 'success' : 'gray')
                                            ->tooltip(fn(Get $get): string => ($get('has_unit_conversion') ?? false) ? 'Disable conversion' : 'Enable conversion')
                                            ->action(function (Set $set, Get $get) {
                                                $currentValue = $get('has_unit_conversion') ?? false;
                                                $set('has_unit_conversion', !$currentValue);


                                                OrderResource::handleConversionToggle($get, $set);
                                                OrderResource::calculateVolumeWeight($get, $set); // This was missing!
                                                $this->updateOrderTotals();
                                            })
                                            ->size('sm')
                                    )
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {

                                        OrderResource::syncUnitsFromWeight($get, $set);
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals();
                                    })
                                    ->columnSpan(1),


                                TextInput::make('freight_length')
                                    ->label('L')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals();
                                    })
                                    ->columnSpan(1),

                                TextInput::make('freight_width')
                                    ->label('W')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals();
                                    })
                                    ->columnSpan(1),

                                TextInput::make('freight_height')
                                    ->label('H')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals();
                                    })
                                    ->columnSpan(1),

                                Select::make('dimension_unit')
                                    ->label('Dim Unit')
                                    ->options(['in' => 'IN', 'cm' => 'CM'])
                                    ->default('in')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        // When dimension unit changes, sync weight units AND recalculate
                                        OrderResource::syncUnitsFromDimension($get, $set);
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals();
                                    })
                                    ->columnSpan(1),


                                Toggle::make('is_stackable')
                                    ->label('Not Stack')
                                    ->inline(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        OrderResource::calculateVolumeWeight($get, $set);
                                        $this->updateOrderTotals();
                                    })
                                    ->columnSpan(1),
                                // Hidden fields
                                Hidden::make('freight_chargeable_weight')
                                    ->default(0),
                                Hidden::make('has_unit_conversion')
                                    ->default(false),
                            ]),
                        ])
                        ->itemLabel(function (array $state): ?string {
                            $type = $state['freight_type'] ?? 'Item';
                            $pieces = $state['freight_pieces'] ?? '';
                            $weight = $state['freight_weight'] ?? '';
                            $unit = $state['weight_unit'] ?? 'lbs';

                            return "{$type}: {$pieces} pcs, {$weight} {$unit}";
                        })
                        ->reorderable(false)
                        ->collapsible()
                        ->cloneable()
                        ->afterStateUpdated(function () {
                            // Trigger calculations when items are added/removed
                            $this->updateOrderTotals();
                        }),


                    Section::make('Freight Calculations')
                        ->headerActions([
                            Action::make('toggleManualSkids')
                                ->label(fn(Get $get): string => $get('manual_skids') ? 'Manual Mode: ON' : 'Manual Mode: OFF')
                                ->icon(fn(Get $get): string => $get('manual_skids') ? 'heroicon-m-lock-closed' : 'heroicon-m-calculator')
                                ->color(fn(Get $get): string => $get('manual_skids') ? 'warning' : 'primary')
                                ->badge(fn(Get $get): ?string => $get('manual_skids') ? 'MANUAL' : null)
                                ->badgeColor('warning')
                                ->action(function (Set $set, Get $get) {
                                    $currentValue = $get('manual_skids') ?? false;
                                    $set('manual_skids', !$currentValue);
                                    $this->handleManualSkidsToggle($get, $set);
                                })
                                ->tooltip(
                                    fn(Get $get): string => $get('manual_skids')
                                        ? 'Switch to automatic calculation'
                                        : 'Enable manual override for chargeable skids'
                                )
                                ->size('sm'),
                        ])
                        ->schema([
                            Grid::make(6)->schema([
                                TextInput::make('total_pieces_calc')
                                    ->label('Total Pieces')
                                    ->disabled()
                                    ->default(0)
//                                    ->content(fn() => $this->totalPieces)
                                    ->extraAttributes([
                                        'class' => 'bg-gray-50 p-1 rounded-lg text-center border border-gray-200'
                                    ]),

                                Group::make([
                                    TextInput::make('manual_total_chargeable_pieces')
                                        ->label('Manual Chargeable Skids')
                                        ->numeric()
                                        ->live()
                                        ->default(0)
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            $this->updateOrderTotals();
                                        })
                                        ->extraAttributes([
                                            'class' => 'text-center'
                                        ])
                                        ->extraInputAttributes([
                                            'class' => 'bg-gray-50 p-1 rounded-lg text-center border border-gray-200'
                                        ])
                                        ->disabled(fn(Get $get): bool => !$get('manual_skids') ?? false),

//                                    Placeholder::make('total_chargeable_skids_calc')
//                                        ->label('Total Chargeable Skids')
//                                        ->content(fn() => $this->totalChargeablePieces)
//                                        ->extraAttributes([
//                                            'class' => 'bg-gray-50 p-1 rounded-lg text-center border border-gray-200'
//                                        ])
//                                        ->visible(fn(Get $get): bool => !($get('manual_skids') ?? false)),
                                ]),

                                TextInput::make('total_actual_weight_calc')
                                    ->label('Total Actual Weight')
                                    ->disabled()
//                                    ->content(fn() => $this->totalActualWeight)
                                    ->extraAttributes([
                                        'class' => 'bg-gray-50 p-1 rounded-lg text-center border border-gray-200'
                                    ]),

                                Placeholder::make('total_volume_weight_calc')
                                    ->label('Total Volume Weight')
                                    ->content(fn() => $this->totalVolumeWeight)
                                    ->extraAttributes([
                                        'class' => 'bg-gray-50 p-1 rounded-lg text-center border border-gray-200'
                                    ]),

                                Placeholder::make('total_chargeable_weight_calc')
                                    ->label('Total Chargeable Weight')
                                    ->content(fn() => $this->totalChargeableWeight)
                                    ->extraAttributes([
                                        'class' => 'bg-gray-50 p-1 rounded-lg text-center border border-gray-200'
                                    ]),

                                Placeholder::make('weight_in_kg_calc')
                                    ->label('Weight in KG')
                                    ->content(fn() => $this->weightInKg)
                                    ->extraAttributes([
                                        'class' => 'bg-gray-50 p-1 rounded-lg text-center border border-gray-200'
                                    ]),
                            ])
                                ->extraAttributes(['class' => 'gap-3']),

                            // Hidden field to store the manual skids state
                            Hidden::make('manual_skids')
                                ->default(false),
                        ])
                        ->compact()
                ]),



            // ORDER SUMMARY SECTION


            // CHARGES SECTION - Customer Vehicles Only with Toggles
            Grid::make(2)
                ->extraAttributes(['class' => 'auto-rows-fr gap-x-4'])
                ->schema([
                    Section::make('Freight & Charges')->key("freight_and_other_charges")
                        ->columnSpan(1)
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->schema([
                            // Top level charge type toggles
                            Grid::make(3)->schema([
                                Toggle::make('no_charges')
                                    ->label('No Charges')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $set('manual_charges', false);
                                            $set('manual_fuel_surcharges', false);
                                            $set('direct_km', 0);
                                            $this->resetVehicleTypes($set, $get);
                                            $set('freight_rate_amount', 0.00);
                                            $set('freight_rate_display', 0.00);
                                            $set('fuel_surcharge_amount', 0.00);
                                            $set('fuel_surcharge_display', 0.00);
                                        }
                                        $this->updateOrderTotals();
                                    }),

                                Toggle::make('manual_charges')
                                    ->label('Manual Charges')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $set('no_charges', false);
                                        }
                                        $this->updateOrderTotals();
                                    }),

                                Toggle::make('manual_fuel_surcharges')
                                    ->label('Manual Fuel Surcharges')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $set('no_charges', false);
                                        }
                                        $this->updateOrderTotals();
                                    }),
                            ]),

                            // SERVICE CHARGES Section
                            Section::make('SERVICE CHARGES')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('direct_km')
                                            ->label('Direct KM')
                                            ->numeric()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
//                                                $this->calculateServiceCharges($get, $set);
                                                $this->updateOrderTotals();
                                            })
                                            ->inlineLabel()
                                            ->columnSpan(1),

                                        Group::make([])->columnSpan(1), // Spacer
                                    ]),
                                ])
                                ->visible(fn(Get $get) => $get('service_type') === 'direct' && !$get('no_charges'))
                                ->collapsible()
                                ->collapsed(true),

                            // Vehicle Type Section - Only Customer's Included Vehicles

                            Section::make('Vehicle Type')
                                ->schema([
                                    // Data rows
                                    Repeater::make('customer_vehicle_types')
                                        ->label('')
                                        ->schema([
                                            Grid::make(4)->schema([
                                                Placeholder::make('accessorial_display')
                                                    ->label('')
                                                    ->content(fn($get) => $get('vehicle_name'))
                                                    ->extraAttributes(['class' => 'text-sm text-gray-900 dark:text-gray-100'])
                                                    ->columnSpan(2),

                                                Group::make([
                                                    Toggle::make('is_selected')
                                                        ->label('')
                                                        ->hiddenLabel()
                                                        ->inline(false)
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $set, $get) {
                                                            $this->updateOrderTotals();
                                                        }),
                                                ])->extraAttributes(['class' => 'flex justify-center'])
                                                    ->columnSpan(1),

                                                Group::make([
                                                    TextInput::make('base_rate')
                                                        ->label('')
                                                        ->hiddenLabel()
                                                        ->disabled()
                                                        ->dehydrated(false)
                                                        ->prefix('$')
                                                        ->extraAttributes(['class' => 'bg-transparent border-0 p-0 text-sm text-center']),
                                                ])->extraAttributes(['class' => 'flex justify-center'])
                                                    ->columnSpan(1),
                                            ]),

                                            Hidden::make('vehicle_type_id'),
                                        ])
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->collapsible(false)
                                        ->itemLabel('')
                                        ->extraAttributes(['class' => 'space-y-1'])
                                        ->default([]),
                                ])
                                ->visible(fn(Get $get) => $get('service_type') === 'direct' && !$get('no_charges'))
                                ->collapsible()
                                ->collapsed(true),
                            // Freight Charges Section (Name | Qty | Amount format)
                            Section::make('Freight & Charges')->key("freight_charges")
                                ->schema([
                                    Grid::make(2)->schema([
                                        // Freight Rate
                                        TextInput::make('freight_rate_amount')
                                            ->label('Freight Rate')
                                            ->numeric()
                                            ->prefix('$')
                                            ->readOnly(fn($get) => !$get('manual_charges'))
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function () {
                                                $this->updateOrderTotals();
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('fuel_surcharge_amount')
                                            ->label('Fuel Surcharge')
                                            ->numeric()
                                            ->prefix('$')
                                            ->readOnly(fn($get) => !$get('manual_fuel_surcharges'))
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function () {
                                                $this->updateOrderTotals();
                                            })
                                            ->columnSpan(1),
                                    ]),



                                ])
                                ->visible(fn(Get $get) => !$get('no_charges')),


                            Section::make('Customer Accessorial Charges')
                                ->columnSpan(1)
                                // ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                                ->description('Customer-specific accessorial charges and fees')
                                ->schema([
                                    Repeater::make('customer_accessorials')
                                        ->label('')
                                        ->schema([
                                            Grid::make(5)->schema([
                                                Placeholder::make('accessorial_display')
                                                    ->label('')
                                                    ->content(fn($get) => $get('accessorial_name'))
                                                    ->extraAttributes(['class' => 'text-sm text-gray-900 dark:text-gray-100'])
                                                    ->columnSpan(2),

                                                Group::make([
                                                    Toggle::make('is_included')
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $set, $get) {
                                                            if ($state) {
                                                                // 🔥 ELECTRON WAY: Just calculate, don't change qty
                                                                $this->calculateAccessorialAmount($get, $set);
                                                            } else {
                                                                $set('qty', 0);
                                                                $set('calculated_amount', 0);
                                                                $this->updateOrderTotals();
                                                            }
                                                        }),
                                                ])->extraAttributes(['class' => 'flex justify-center'])
                                                    ->columnSpan(1),

                                                Group::make([
                                                    TextInput::make('qty')
                                                        ->label('')
                                                        ->hiddenLabel()
                                                        ->numeric()
                                                        ->default(0)
                                                        ->minValue(0)
                                                        ->step(1)
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function ($get, $set) {
                                                            $this->calculateAccessorialAmount($get, $set);
                                                        })
                                                        ->extraAttributes(['class' => 'w-16 text-center']),
                                                ])->extraAttributes(['class' => 'flex justify-center'])
                                                    ->columnSpan(1),

                                                Group::make([
                                                    TextInput::make('calculated_amount')
                                                        ->label('')
                                                        ->hiddenLabel()
                                                        ->disabled()
                                                        ->dehydrated(true)
                                                        ->prefix('$')
                                                        ->formatStateUsing(function ($state) {
                                                            return number_format($state ?: 0, 2);
                                                        })
                                                        ->extraAttributes(['class' => 'bg-transparent border-0 p-0 text-sm text-center']),
                                                ])->extraAttributes(['class' => 'flex justify-center'])
                                                    ->columnSpan(1),
                                            ]),

                                            Hidden::make('accessorial_id'),
                                            Hidden::make('accessorial_name'),
                                            Hidden::make('rate'),
                                            Hidden::make('min'),
                                            Hidden::make('max'),
                                            Hidden::make('free_time'),
                                            Hidden::make('base_amount'),
                                            Hidden::make('type'),
                                            Hidden::make('time_unit'),
                                            Hidden::make('amount_type'),
                                        ])
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->itemLabel(null)
                                        ->extraAttributes(['class' => 'text-sm text-gray-900 dark:text-gray-100 flex items-center'])
                                        ->default([]),

                                    Section::make('Additional Service Charges')
                                        ->description('Add custom charges not covered by standard accessorials')
                                        ->schema([
                                            Repeater::make('service_charges')
                                                ->label('')
                                                ->schema([
                                                    Grid::make(6)->schema([
                                                        TextInput::make('charge_name')
                                                            ->label('')
                                                            ->hiddenLabel()
                                                            ->columnSpan(3)
                                                            ->placeholder('Charge name')
                                                            ->extraAttributes(['class' => 'text-sm']),

                                                        TextInput::make('charge_qty')
                                                            ->label('')
                                                            ->hiddenLabel()
                                                            ->numeric()
                                                            ->default(1)
                                                            ->columnSpan(1)
                                                            ->placeholder('Qty')
                                                            ->extraAttributes(['class' => 'text-center']),

                                                        TextInput::make('charge_amount')
                                                            ->label('')
                                                            ->hiddenLabel()
                                                            ->numeric()
                                                            ->prefix('$')
                                                            ->columnSpan(2)
                                                            ->placeholder('Amount')
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function ($state) {
                                                                if ($state) $this->updateOrderTotals();
                                                            })
                                                            ->extraAttributes(['class' => 'text-center']),
                                                    ]),
                                                ])
                                                ->addActionLabel('Add Service Charge')
                                                ->defaultItems(0)
                                                ->itemLabel(null)
                                                ->reorderable(false)
                                                ->cloneable(false)
                                                ->extraAttributes(['class' => 'space-y-1']),
                                        ])
                                        ->collapsible()
                                        ->collapsed(true)
                                        ->extraAttributes([
                                            'class' => 'service-charges-section',
                                            'id' => 'additional-service-charges'
                                        ]),

                                ]),

                            Section::make('Order Summary')
                                ->schema([
                                    // Weight Information Section
                                    Section::make('Weight Calculations')
                                        ->schema([
                                            Grid::make(3)->schema([
                                                Placeholder::make('total_actual_weight_calc')
                                                    ->label('Actual Weight')
                                                    ->content(fn() => $this->totalActualWeight)
                                                    ->extraAttributes([
                                                        'class' => 'bg-gray-50 p-2 rounded-lg text-center border border-gray-200'
                                                    ]),

                                                Placeholder::make('total_volume_weight_calc')
                                                    ->label('Volume Weight')
                                                    ->content(fn() => $this->totalVolumeWeight)
                                                    ->extraAttributes([
                                                        'class' => 'bg-gray-50 p-2 rounded-lg text-center border border-gray-200'
                                                    ]),

                                                Placeholder::make('total_chargeable_weight_calc')
                                                    ->label('Chargeable Weight')
                                                    ->content(fn() => $this->totalChargeableWeight)
                                                    ->extraAttributes([
                                                        'class' => 'bg-gray-50 p-2 rounded-lg text-center border border-gray-200'
                                                    ]),
                                            ])->extraAttributes(['class' => 'gap-4']),

                                            Grid::make(2)->schema([
                                                Placeholder::make('total_chargeable_pieces_calc')
                                                    ->label('Total Pieces')
                                                    ->content(fn() => $this->totalChargeablePieces)
                                                    ->extraAttributes([
                                                        'class' => 'bg-gray-50 p-2 rounded-lg text-center border border-gray-200'
                                                    ]),

                                                Placeholder::make('weight_in_kg_calc')
                                                    ->label('Weight (KG)')
                                                    ->content(fn() => $this->weightInKg)
                                                    ->extraAttributes([
                                                        'class' => 'bg-gray-50 p-2 rounded-lg text-center border border-gray-200'
                                                    ]),
                                            ])->extraAttributes(['class' => 'gap-4']),
                                        ])
                                        ->compact()
                                        ->extraAttributes(['class' => 'mb-6']),

                                    // Pricing Breakdown Section
                                    Section::make('Pricing Breakdown')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                Placeholder::make('freight_rate_display')
                                                    ->label('Freight Rate')
                                                    ->content(fn() => $this->freightRateAmount)
                                                    ->extraAttributes([
                                                        'class' => 'flex justify-between items-center p-2 bg-gray-50 rounded-lg border border-gray-200'
                                                    ]),

                                                Placeholder::make('fuel_surcharge_display')
                                                    ->label('Fuel Surcharge')
                                                    ->content(fn() => $this->fuelSurchargeAmount)
                                                    ->extraAttributes([
                                                        'class' => 'flex justify-between items-center p-2 bg-gray-50 rounded-lg border border-gray-200'
                                                    ]),
                                            ])->extraAttributes(['class' => 'gap-4 mb-4']),

                                            Placeholder::make('sub_total_display')
                                                ->label('Sub Total')
                                                ->content(fn() => $this->subTotalAmount)
                                                ->extraAttributes([
                                                    'class' => 'flex justify-between items-center p-4 bg-green-50 rounded-lg border-2 border-green-300 text-xl font-bold text-green-800'
                                                ]),

                                            Grid::make(2)->schema([
                                                Placeholder::make('provincial_tax_display')
                                                    ->label('Provincial Tax')
                                                    ->content(fn() => $this->provincialTaxAmount)
                                                    ->extraAttributes([
                                                        'class' => 'flex justify-between items-center p-2 bg-gray-50 rounded-lg border border-gray-200'
                                                    ]),

                                                Placeholder::make('federal_tax_display')
                                                    ->label('Federal Tax')
                                                    ->content(fn() => $this->federalTaxAmount)
                                                    ->extraAttributes([
                                                        'class' => 'flex justify-between items-center p-2 bg-gray-50 rounded-lg border border-gray-200'
                                                    ]),
                                            ])->extraAttributes(['class' => 'gap-4 mb-6']),

                                            Placeholder::make('grand_total_display')
                                                ->label('Grand Total')
                                                ->content(fn() => $this->grandTotalAmount)
                                                ->extraAttributes([
                                                    'class' => 'flex justify-between items-center p-4 bg-green-50 rounded-lg border-2 border-green-300 text-xl font-bold text-green-800'
                                                ]),
                                        ])
                                        ->compact(),
                                ])
                                ->columnSpan('full')

                        ]),



                    // CUSTOMER ACCESSORIAL CHARGES SECTION - Clean Version, Open by Default

                    Section::make('Waiting Time & Billing')
                        ->columnSpan(1)
                        ->extraAttributes(['class' => 'section-yellow-border h-full flex flex-col'])
                        ->schema([
                            // Pickup Times Row
                            Grid::make(3)->schema([
                                TimePicker::make('pickup_in_time')
                                    ->label('Pickup In')
                                    ->default('00:00')
                                    ->columnSpan(1),
                                TimePicker::make('pickup_out_time')
                                    ->label('Pickup Out')
                                    ->default('00:00')
                                    ->columnSpan(1),
                                DatePicker::make('picked_up_at')
                                    ->label('Picked up at')
                                    ->displayFormat('d/m/Y')
                                    ->columnSpan(1),
                            ]),

                            // Delivery Times Row
                            Grid::make(3)->schema([
                                TimePicker::make('delivery_in_time')
                                    ->label('Delivery In')
                                    ->default('00:00')
                                    ->columnSpan(1),
                                TimePicker::make('delivery_out_time')
                                    ->label('Delivery Out')
                                    ->default('00:00')
                                    ->columnSpan(1),
                                DatePicker::make('delivered_at')
                                    ->label('Delivered at')
                                    ->displayFormat('d/m/Y')
                                    ->columnSpan(1),
                            ]),

                            // Total Times Row
                            Grid::make(3)->schema([
                                Placeholder::make('pickup_total_time')
                                    ->label('Pickup Total time')
                                    ->content('0 mins')
                                    ->columnSpan(1),
                                Placeholder::make('delivery_total_time')
                                    ->label('Delivery Total time')
                                    ->content('0 mins')
                                    ->columnSpan(1),
                                Placeholder::make('overall_total_time')
                                    ->label('Total time')
                                    ->content('0 mins')
                                    ->columnSpan(1),
                            ]),

                            // Signature Fields Row
                            Grid::make(2)->schema([
                                Textarea::make('pickup_signee')
                                    ->label('Pickup Signee')
                                    ->rows(3)
                                    ->placeholder('Pickup signature details...')
                                    ->columnSpan(1),
                                Textarea::make('delivery_signee')
                                    ->label('Delivery Signee')
                                    ->rows(3)
                                    ->placeholder('Delivery signature details...')
                                    ->columnSpan(1),
                            ]),


                            // BILLING SECTION
                            Section::make('Billing')
                                ->columnSpan(1)

                                ->schema([
                                    Grid::make(3)->schema([
                                        DatePicker::make('invoice_date')
                                            ->label('Invoice Date')
                                            ->default(now())
                                            ->displayFormat('d/m/Y')
                                            ->columnSpan(1),
                                        TextInput::make('invoice_number')
                                            ->label('Invoice#')
                                            ->placeholder('Auto-generated')
                                            ->columnSpan(1),
                                        Toggle::make('invoiced')
                                            ->label('Invoiced')
                                            ->columnSpan(1),
                                    ]),
                                ])
                                ->collapsible()
                                ->collapsed(false),
                        ])
                        ->collapsible()
                        ->collapsed(false),

                ]),







        ])->columns(1);
    }

    // Add these methods to your CreateOrder class

    protected function calculateServiceCharges(Get $get, Set $set): void
    {
        $directKm = (float) $get('direct_km') ?: 0;
        $totalServiceCharge = 0;

        $vehicleTypes = $get('customer_vehicle_types') ?: [];

        foreach ($vehicleTypes as $index => $vehicle) {
            if ($vehicle['is_selected'] ?? false) {
                $rate = (float) $vehicle['base_rate'] ?: 0;
                $totalServiceCharge += $rate * $directKm;
            }
        }

        // You can set this to a total field if needed
        // $set('total_service_charge', $totalServiceCharge);
    }

    protected function resetVehicleTypes($set, $get): void
    {
        $vehicleTypes = $get('customer_vehicle_types') ?: [];

        foreach ($vehicleTypes as $index => $vehicle) {
            $set("customer_vehicle_types.{$index}.is_selected", false);
        }
    }


    protected function loadCustomerData($customerId, Set $set): void
    {
        // Always use cached data from selectedCustomerData
        $this->loadCustomerVehicleTypesFromCache($set);
        $this->loadCustomerAccessorialsFromCache($set);
    }

    protected function generateOrderNumber(): string
    {
        $date = now()->format('ymd');
        $count = Order::whereDate('created_at', today())->count() + 1;
        return 'T-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }



    protected function calculateAccessorialAmount(Get $get, Set $set): void
    {
        $qty = (float) $get('qty');
        $rate = (float) $get('rate');
        $min = (float) $get('min');
        $max = (float) $get('max');

        //ELECTRON WAY: Default qty to 1 if it's 0 (matches Electron logic)
        if ($qty == 0) {
            $qty = 1;
            $set('qty', $qty);
        }

        // NEW: Get additional metadata for advanced calculations
        $type = $get('type') ?? 'fixed_price';
        $freeTime = (float) $get('free_time');
        $baseAmount = (float) $get('base_amount');
        $timeUnit = $get('time_unit') ?? 'minute';
        $amountType = $get('amount_type') ?? 'fixed';

        //Get current form state for complex calculations
        $formState = $this->form->getState();
        $freightRate = (float) ($formState['freight_rate_amount'] ?? 0);
        $totalWaitingTime = $this->calculateTotalWaitingTime($formState);

        $calculatedAmount = 0;
        //Implement the 4 calculation types from Electron
        switch ($type) {
            case 'fixed_price':
                $calculatedAmount = $rate;
                break;

            case 'fuel_based':
                if ($amountType === 'percentage') {
                    // Percentage of freight rate
                    $calculatedAmount = ($rate / 100) * $freightRate;
                } else {
                    // Fixed amount
                    $calculatedAmount = $rate;
                }
                // No qty multiplier for fuel-based charges
                break;

            case 'transport_based':
                // 🔥 ELECTRON WAY: Simple freight rate calculation only
                if ($amountType === 'percentage') {
                    // Percentage of freight rate only
                    $calculatedAmount = ($rate / 100) * $freightRate;
                } else {
                    // Rate multiplied by freight rate
                    $calculatedAmount = $rate * $freightRate;
                }
                break;

            case 'time_based':
                // ELECTRON WAY: Qty is a multiplier, not the waiting time
                // Convert free time to minutes if needed
                $freeTimeMinutes = ($timeUnit === 'minute') ? $freeTime : $freeTime * 60;

                // Get total waiting time from pickup/delivery in/out times
                $totalWaitingMinutes = $totalWaitingTime; // This comes from calculateTotalWaitingTime()

                // Calculate chargeable time (total waiting time minus free time)
                $chargeableTime = max(0, $totalWaitingMinutes - $freeTimeMinutes);

                //ELECTRON FORMULA: base_amount + (rate * qty * chargeable_time)
                if ($baseAmount > 0) {
                    $calculatedAmount = $baseAmount + ($rate * $chargeableTime);
                } else {
                    $calculatedAmount = $rate * $chargeableTime;
                }
                break;
        }

        $calculatedAmount *= $qty;
        // Apply min/max constraints (existing logic)
        if ($min > 0 && $calculatedAmount < $min) {
            $calculatedAmount = $min;
        }
        if ($max > 0 && $calculatedAmount > $max) {
            $calculatedAmount = $max;
        }

        $set('calculated_amount', round($calculatedAmount, 2));
        $this->updateOrderTotals();
    }


    /**
     * Calculate total waiting time from pickup and delivery in/out times
     */
    protected function calculateTotalWaitingTime(array $formState): float
    {
        $pickupIn = $formState['pickup_in_time'] ?? null;
        $pickupOut = $formState['pickup_out_time'] ?? null;
        $deliveryIn = $formState['delivery_in_time'] ?? null;
        $deliveryOut = $formState['delivery_out_time'] ?? null;

        $totalMinutes = 0;

        // Calculate pickup waiting time
        if ($pickupIn && $pickupOut) {
            $totalMinutes += $this->calculateTimeDifferenceInMinutes($pickupIn, $pickupOut);
        }

        // Calculate delivery waiting time
        if ($deliveryIn && $deliveryOut) {
            $totalMinutes += $this->calculateTimeDifferenceInMinutes($deliveryIn, $deliveryOut);
        }

        return (float) $totalMinutes;
    }

    /**
     * Calculate time difference between two times in minutes
     */
    protected function calculateTimeDifferenceInMinutes(string $timeIn, string $timeOut): int
    {
        try {
            $timeStart = Carbon::createFromFormat('H:i', $timeIn);
            $timeEnd = Carbon::createFromFormat('H:i', $timeOut);

            // If end time is before start time, assume it's next day
            if ($timeEnd->lt($timeStart)) {
                $timeEnd->addDay();
            }

            return $timeStart->diffInMinutes($timeEnd);
        } catch (Exception $e) {
            return 0; // Return 0 if there's any parsing error
        }
    }

    /**
     * Calculate current total of all accessorial charges (for transport_based)
     */
    protected function calculateCurrentAccessorialTotal(): float
    {
        $formState = $this->form->getState();
        $accessorials = $formState['customer_accessorials'] ?? [];

        $total = 0;
        foreach ($accessorials as $accessorial) {
            if (($accessorial['is_included'] ?? false) && $accessorial['type'] !== 'transport_based') {
                $total += (float) ($accessorial['calculated_amount'] ?? 0);
            }
        }

        return $total;
    }




    protected function loadCustomerVehicleTypesFromCache(Set $set): void
    {
        if (!isset($this->selectedCustomerData['customer'])) {
            $set('customer_vehicle_types', []);
            return;
        }

        $customer = $this->selectedCustomerData['customer'];
        $vehicleData = [];

        // Convert to array first to avoid collection method issues
//        $vehicleTypes = $customer->vehicleTypes->toArray();
//
//        foreach ($vehicleTypes as $vehicleType) {
//            $vehicleData[] = [
//                'vehicle_type_id' => $vehicleType['id'],
//                'vehicle_name' => $vehicleType['name'],
//                'is_selected' => false,
//                'base_rate' => $vehicleType['pivot']['rate'] ?? $vehicleType['rate'] ?? 0,
//                'vehicle_rate' => $vehicleType['pivot']['rate'] ?? $vehicleType['rate'] ?? 0,
//            ];
//        }

        $vehicleTypes = DB::table("vehicle_types")
            ->leftJoin('customer_vehicle_type', function ($join) use ($customer) {
                $join->on('customer_vehicle_type.vehicle_type_id', '=', 'vehicle_types.id')->where('customer_vehicle_type.customer_id', '=', $customer->id);
            })
            ->select('vehicle_types.id', 'vehicle_types.name', 'vehicle_types.rate as base_rate', 'customer_vehicle_type.customer_id', 'customer_vehicle_type.rate as vehicle_rate')
            ->get()->toArray();
        foreach ($vehicleTypes as $vehicleType) {
            $vehicleType = (array) $vehicleType;
            $vehicleData[] = [
                'vehicle_type_id' => $vehicleType['id'],
                'vehicle_name' => $vehicleType['name'],
                'is_selected' => false,
                'base_rate' => $vehicleType['base_rate'] ?? 0,
                'vehicle_rate' => !empty($vehicleType['customer_id']) ? $vehicleType['vehicle_rate'] : $vehicleType['base_rate'],
                'customer_id' => $vehicleType['customer_id']
            ];
        }

        // Sort by name
        usort($vehicleData, function ($a, $b) {
            return strcasecmp($a['vehicle_name'], $b['vehicle_name']);
        });

        $set('customer_vehicle_types', $vehicleData);
    }

    protected function loadCustomerAccessorialsFromCache(Set $set): void
    {
        if (!isset($this->selectedCustomerData['customer'])) {
            $set('customer_accessorials', []);
            return;
        }

        $customer = $this->selectedCustomerData['customer'];
        $accessorialData = [];

        // Convert to array first to avoid collection method issues
        $accessorials = $customer->accessorials->toArray();

        foreach ($accessorials as $accessorial) {
            $rate = $accessorial['pivot']['amount'] ?? 0;
            $rateDisplay = $rate > 0 ? ' @ $' . number_format($rate, 2) : '';

            $accessorialData[] = [
                'accessorial_id' => $accessorial['id'],
                'accessorial_name' => $accessorial['name'] . $rateDisplay,
                'is_included' => false,
                'qty' => 0,
                'rate' => $rate,
                'calculated_amount' => 0,
                'min' => $accessorial['pivot']['min'] ?? 0,
                'max' => $accessorial['pivot']['max'] ?? 0,
                'free_time' => $accessorial['pivot']['free_time'] ?? 0,
                'base_amount' => $accessorial['pivot']['base_amount'] ?? 0,

                // 🔥 ADD THESE 3 NEW LINES:
                'type' => $accessorial['type'] ?? 'fixed_price',
                'time_unit' => $accessorial['time_unit'] ?? 'minute',
                'amount_type' => $accessorial['amount_type'] ?? 'fixed',
            ];
        }

        $set('customer_accessorials', $accessorialData);
    }

    protected function getAccessorialName($accessorialId)
    {
        if (!$accessorialId) return '';

        $accessorial = Accessorial::find($accessorialId);
        return $accessorial ? $accessorial->name : '';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['status'] = $data['is_quote'] ?? false ? 'quote' : 'entered';

        //Extract freight data for separate creation
        $freightData = $data['freights'] ?? [];
        unset($data['freights']); // Remove from main order data

        // Store freight data temporarily for afterCreate
        $this->tempFreightData = $freightData;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Create freight records after the main order is created
        if (!empty($this->tempFreightData)) {
            foreach ($this->tempFreightData as $freightData) {
                $this->record->freights()->create([
                    'freight_type' => $freightData['freight_type'] ?? 'skid',
                    'freight_description' => $freightData['freight_description'] ?? 'FAK',
                    'freight_pieces' => $freightData['freight_pieces'] ?? 1,
                    'freight_weight' => $freightData['freight_weight'] ?? 0,
                    'weight_unit' => $freightData['weight_unit'] ?? 'lbs',
                    'freight_chargeable_weight' => $freightData['freight_chargeable_weight'] ?? 0,
                    'freight_length' => $freightData['freight_length'] ?? 0,
                    'freight_width' => $freightData['freight_width'] ?? 0,
                    'freight_height' => $freightData['freight_height'] ?? 0,
                    'dimension_unit' => $freightData['dimension_unit'] ?? 'in',
                    'is_stackable' => $freightData['is_stackable'] ?? false,
                    'stackable_value' => $freightData['is_stackable'] ? 1 : 0,
                    'has_unit_conversion' => $freightData['has_unit_conversion'] ?? false,
                ]);
            }
        }

        parent::afterCreate();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Order')
                ->action('create')
                ->color('primary'),
            Action::make('save_as_quote')
                ->label('Save as Quote')
                ->color('gray')
                ->action(function () {
                    $data = $this->form->getState();
                    $data['is_quote'] = true;
                    $this->handleRecordCreation($data);
                }),
            Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }



    protected function updateOrderTotals(): void
    {
        $formState = $this->form->getState();
        $manualWeight = $formState['manual_weight'] ?? false;
        $manualSkids = $formState['manual_skids'] ?? false;

        // Build selectedCustomerData with rate sheets from cache
        $selectedCustomerData = $this->buildSelectedCustomerData();

        // Check if we have customer data
        if (empty($selectedCustomerData)) {
            Log::warning('No customer data available for calculations');
            return;
        }

        // Step 1: Calculate totals
        $totals = $this->calculateOrderTotalsWithManualOverrides($formState, $manualWeight, $manualSkids);

        // Step 2: Calculate BASE freight rates
        $rateResults = OrderResource::calculateFreightRates($totals, $selectedCustomerData, $formState);

        // Step 3: Initialize freight rate
        $currentFreightRate = $rateResults['freight_rate'];

        // Step 4: Apply Service Charges
        $serviceCharges = OrderResource::calculateServiceCharges($formState, $selectedCustomerData, $currentFreightRate);
        $serviceType = $formState['service_type'] ?? 'regular'; // FIX: Use 'regular' as default (matches your form options)

        // Apply Electron's service charge integration logic
        if ($serviceType === 'rush') {
            // Rush service calculated as percentage of current freight rate
            $rushServiceCharge = $serviceCharges['rush_service_charge'];
            $currentFreightRate = $currentFreightRate + $rushServiceCharge;  // ADD to freight rate
        } elseif ($serviceType === 'direct') {
            // Direct service REPLACES freight rate
            $currentFreightRate = $serviceCharges['direct_service_charge'];  // OVERWRITE freight rate
        }

        // Calculate customer accessorial charges
        $accessorialCharges = OrderResource::calculateAccessorialCharges($formState);

        $fuelFreightRate = $currentFreightRate + $accessorialCharges['fuel_based_accessorial_charges'];
        // Step 5: Calculate Fuel Surcharge on MODIFIED freight rate
        $fuelSurcharge = OrderResource::calculateFuelSurcharge(
            $fuelFreightRate,
            $totals['total_chargeable_weight'],
            $selectedCustomerData,
            $formState
        );

        // Step 6: Calculate Sub Total
        $subTotal = $currentFreightRate + $fuelSurcharge + $accessorialCharges['total_accessorial_charges'];

        // Step 7: Calculate Taxes on Sub Total
        $taxes = OrderResource::calculateProvincialTaxes(
            $subTotal,
            $formState['receiver_province'] ?? '',
            $selectedCustomerData
        );

        // Step 8: Calculate Grand Total
        $grandTotal = $subTotal + $taxes['pst'] + $taxes['gst'];

        // Build results array
        $finalResults = [
            // Display values (for UI)
            'base_freight_rate' => $rateResults['freight_rate'],  // Original freight rate
            'service_charges' => $serviceCharges,  // Service charges breakdown
            'modified_freight_rate' => $currentFreightRate,  // Freight rate after service integration
            'fuel_surcharge' => $fuelSurcharge,
            'taxes' => $taxes,
            'sub_total' => $subTotal,
            'grand_total' => $grandTotal,
            "no_charges" => $formState["no_charges"],
            // For backward compatibility with your current display logic
            'freight_rate' => $currentFreightRate,  // This now includes service charges
            'total_freight_rate' => $currentFreightRate  // Same as freight_rate
        ];

        // Update all display components
        $this->updateAllDisplays($totals, $finalResults);
    }

    //Build selectedCustomerData from cache
    protected function buildSelectedCustomerData(): array
    {
        if (!$this->selectedCustomerId) {
            return [];
        }

        // Get rate sheets from cache instead of component property
        $rateSheets = Cache::get("customer_rates:{$this->selectedCustomerId}", []);

        //Build the structure that your calculations expect
        return [
            'customer' => $this->selectedCustomerData['customer'] ?? null,
            'rate_sheets' => $rateSheets, // From cache
            'accessorials' => $this->selectedCustomerData['accessorials'] ?? [],
            'vehicle_types' => $this->selectedCustomerData['vehicle_types'] ?? [],
            'billing_settings' => $this->selectedCustomerData['billing_settings'] ?? [],
        ];
    }


    protected function updateAllDisplays(array $totals, array $rateResults): void
    {
        try {
            // Calculate weights exactly like Electron
            $this->totalActualWeight = number_format($totals['total_actual_weight'], 2);

            //Total Volume Weight = sum of pure volume weights (like Electron)
            $this->totalVolumeWeight = number_format($totals['pure_total_volume_weight'], 2);

            //Total Chargeable Weight = max(actual, volume) per Electron logic
            $this->totalChargeableWeight = number_format($totals['total_chargeable_weight'], 2);

            $this->totalChargeablePieces = $totals['total_chargeable_pieces'];
            $this->totalPieces = $totals['total_pieces'];
            $this->weightInKg = number_format($totals['weight_in_kg'], 2) . ' kg';

            $this->freightRateAmount = number_format($rateResults['freight_rate'], 2);
            $this->fuelSurchargeAmount = number_format($rateResults['fuel_surcharge'], 2);
            $this->provincialTaxAmount = number_format($rateResults['taxes']['pst'], 2);
            $this->federalTaxAmount = number_format($rateResults['taxes']['gst'], 2);
            $this->subTotalAmount = number_format($rateResults['sub_total'], 2);
            $this->grandTotalAmount = number_format($rateResults['grand_total'], 2);

            if (!$rateResults["no_charges"])
            {
                $path = $this->form->getStatePath();
                data_set($this, "{$path}.freight_rate_amount", $this->freightRateAmount);
                data_set($this, "{$path}.fuel_surcharge_amount", $this->fuelSurchargeAmount);
//                $this->form->getComponent('data.freight_rate_amount')->state($this->freightRateAmount);
//                $this->form->getComponent('data.fuel_surcharge_amount')->state($this->fuelSurchargeAmount);
            }
            $this->dispatch('$refresh');
        } catch (Exception $e) {
            Log::error('Error updating displays: ' . $e->getMessage());
        }
    }

    protected function handleManualSkidsToggle(Get $get, Set $set): void
    {
        $isManual = $get('manual_skids') ?? false;

        if ($isManual) {
            // Set manual mode and populate current calculated value
            $currentPieces = $this->getCurrentCalculatedPieces();
            $set('manual_total_chargeable_pieces', $currentPieces);
        } else {
            // Return to automatic calculation
            $set('manual_total_chargeable_pieces', null);
            $this->updateOrderTotals();
        }
    }

    protected function handleManualWeightToggle(Get $get, Set $set): void
    {
        $isManual = $get('manual_weight') ?? false;

        if ($isManual) {
            // Set manual mode and populate current calculated value
            $currentWeight = $this->getCurrentCalculatedWeight();
            $set('manual_total_weight', $currentWeight);
        } else {
            // Return to automatic calculation
            $set('manual_total_weight', null);
            $this->updateOrderTotals();
        }
    }

    protected function getCurrentCalculatedPieces(): int
    {
        $formState = $this->form->getState();
        $freights = $formState['freights'] ?? [];

        if (empty($freights) || !isset($this->selectedCustomerData['customer'])) {
            return 0;
        }

        $customer = $this->selectedCustomerData['customer'];
        $weightToPiecesRule = (int) ($customer->weight_to_pieces_rule ?? 1000);
        $totalPiecesFromWeight = 0;
        $totalPiecesFromSize = 0;

        foreach ($freights as $freight) {
            if (empty($freight)) continue;

            $actualWeight = (float) ($freight['freight_weight'] ?? 0);
            $weightUnit = $freight['weight_unit'] ?? 'lbs';
            $freightType = $freight['freight_type'] ?? 'skid';
            $hasConversion = $freight['has_unit_conversion'] ?? false;

            //Apply same conversion logic as old app
            $calculationWeight = $actualWeight;

            $freight = [
                'freight_pieces' => (int) ($freight['freight_pieces'] ?? 0),
                'freight_weight' => $actualWeight,
                'weight_unit' => $weightUnit,
                'has_unit_conversion' => $hasConversion,
                'freight_type' => $freightType,
            ];
            $normalized = UnitConversionService::normalizeToElectronStandard($freight);
            $calculationWeight = $normalized['weight_lbs'];

            $totalPiecesFromWeight += OrderResource::calculatePiecesFromWeight($calculationWeight, $weightToPiecesRule);

            if ($freightType === 'skid') {
                $totalPiecesFromSize += OrderResource::sizeToPieces($freight);
            } else {
                $totalPiecesFromSize += (int) ($freight['freight_pieces'] ?? 0);
            }
        }

        return max($totalPiecesFromWeight, $totalPiecesFromSize);
    }

    protected function getCurrentCalculatedWeight(): float
    {
        $formState = $this->form->getState();
        $freights = $formState['freights'] ?? [];

        $totalActualWeight = 0;
        $totalVolumeWeight = 0;

        foreach ($freights as $freight) {
            if (empty($freight)) continue;

            $actualWeight = (float) ($freight['freight_weight'] ?? 0);
            $volumeWeight = (float) ($freight['freight_chargeable_weight'] ?? 0);
            $weightUnit = $freight['weight_unit'] ?? 'lbs';
            $hasConversion = $freight['has_unit_conversion'] ?? false;

            // 🔥 FIXED: Apply same conversion logic as old app
            $calculationWeight = $actualWeight;

            // WITH THIS:
            $freight = [
                'freight_weight' => $actualWeight,
                'weight_unit' => $weightUnit,
                'has_unit_conversion' => $hasConversion,
            ];
            $normalized = UnitConversionService::normalizeToElectronStandard($freight);
            $calculationWeight = $normalized['weight_lbs'];

            $totalActualWeight += $calculationWeight;
            $totalVolumeWeight += $volumeWeight;
        }

        return max($totalActualWeight, $totalVolumeWeight);
    }

    protected function calculateOrderTotalsWithManualOverrides(array $formState, bool $manualWeight, bool $manualSkids): array
    {
        $freights = $formState['freights'] ?? [];

        if (empty($freights)) {
            return [
                'total_actual_weight' => 0,
                'total_volume_weight' => 0,
                'pure_total_volume_weight' => 0, // ✅ ADD: Missing key
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
        if (isset($this->selectedCustomerData['customer'])) {
            $customerRules['weight_to_pieces_rule'] = $this->selectedCustomerData['customer']->weight_to_pieces_rule ?? 1000;
        }

        // Use the centralized service to get base calculations
        $baseTotals = UnitConversionService::calculateOrderTotals($freights, $customerRules);

        // ✅ FIX: Include ALL keys returned by the service, especially pure_total_volume_weight
        $totals = [
//            'total_actual_weight' => $baseTotals['total_actual_weight'],
//            'total_volume_weight' => $baseTotals['total_volume_weight'],
//            'pure_total_volume_weight' => $baseTotals['pure_total_volume_weight'], // ✅ ADD: This was missing
//            'total_chargeable_weight' => $baseTotals['total_chargeable_weight'],
//            'total_chargeable_pieces' => $baseTotals['total_chargeable_pieces'],
//            'total_pieces' => $baseTotals['total_pieces'],
//            'weight_in_kg' => $baseTotals['weight_in_kg'],
//            'skid_weight' => $baseTotals['skid_weight'],
//            'box_weight' => $baseTotals['box_weight'],
            ...$baseTotals,

            // Additional fields for compatibility
            'pieces_from_weight_rule' => $baseTotals['total_chargeable_pieces'],
            'pieces_from_size_rule' => $baseTotals['total_pieces'],
            'actual_box_weight' => $baseTotals['box_weight'],
            'user_input_pieces' => $baseTotals['total_pieces'],
        ];

        // Apply the existing manual override logic
        if ($manualWeight && isset($formState['manual_total_weight'])) {
            $manualWeightValue = (float) $formState['manual_total_weight'];
            $totals['total_chargeable_weight'] = max($totals['total_chargeable_weight'], $manualWeightValue);
            $totals['skid_weight'] = max($totals['skid_weight'], $manualWeightValue);
            $totals['box_weight'] = max($totals['box_weight'], $manualWeightValue);
        }

        if ($manualSkids && isset($formState['manual_total_chargeable_pieces'])) {
            $totals['total_chargeable_pieces'] = (int) $formState['manual_total_chargeable_pieces'];
        }

        return $totals;
    }
}
