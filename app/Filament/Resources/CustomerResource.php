<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use App\Models\Accessorial;
use Filament\Forms\Components\TagsInput;
use App\Models\VehicleType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use App\Filament\Resources\CustomerResource\RelationManagers\RateSheetsRelationManager;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use Illuminate\Database\Eloquent\Model;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\{TextInput, Toggle, DatePicker, Select, Section, Repeater, Radio, FileUpload};
use Filament\Forms\Components\View;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Hidden;


class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Customers';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)->schema([
                    Grid::make(12)->schema([
                        Grid::make(1)->schema([
                            \Filament\Schemas\Components\Section::make('Basic Information')
                                ->schema([
                                    TextInput::make('account_number')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->autofocus(),
                                    TextInput::make('full_name')->label('Name')->required(),
                                    TextInput::make('address'),
                                    TextInput::make('suite'),
                                    TextInput::make('city'),
                                    TextInput::make('province')
                                        ->maxLength(2)
                                        ->minLength(2)
                                        ->rule('regex:/^[A-Z]{2}$/')
                                        ->placeholder('e.g., ON')
                                        ->label('Province')
                                        ->helperText('Enter 2-letter uppercase code'),
                                    TextInput::make('postal_code'),
                                    TextInput::make('account_contact'),
                                    TextInput::make('telephone_number'),
                                    TextInput::make('fax_number'),
                                ])->columns(3)->extraAttributes([
                                    'style' => 'border-top: 2px solid #fcb410; border-radius: 0;',
                                ]),
                            \Filament\Schemas\Components\Section::make('Payment Information')
                                ->schema([
                                    TextInput::make('terms_of_payment'),
                                    TextInput::make('weight_to_pieces_rule'),
                                    TextInput::make('fuel_surcharge_rule')->numeric(),
                                    DatePicker::make('account_opening_date'),
                                    DatePicker::make('last_invoice_date')->disabled(),
                                    DatePicker::make('last_payment_date')->disabled(),
                                    TextInput::make('credit_limit')->numeric(),
                                    TextInput::make('account_balance')->disabled(),
                                ])->columns(3)->extraAttributes([
                                    'style' => 'border-top: 2px solid #fcb410; border-radius: 0;',
                                ]),
                            \Filament\Schemas\Components\Section::make('Other')
                                ->description('Set language, invoicing frequency, rush charges, and custom logo')
                                ->schema([
                                    Radio::make('language')->options(['english' => 'English', 'french' => 'French']),
                                    Radio::make('invoicing')->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly']),
                                    FileUpload::make('custom_logo')->disk('public')->directory('customers/logos')->image(),
                                    // TextInput::make('rush_service_charge')->numeric(),
                                    // TextInput::make('rush_service_charge_min')->numeric(),
                                ])->columns(3)->extraAttributes([
                                    'style' => 'border-top: 2px solid #fcb410; border-radius: 0;',
                                ])->collapsible(),
                            \Filament\Schemas\Components\Section::make('Accessorial Charges')
                                ->description(
                                    fn($get) =>
                                    collect($get('accessorials'))->contains('included', true)
                                        ? 'Accessorial charges enabled for this customer'
                                        : 'Enable and customize accessorial charges per customer'
                                )
                                ->schema([
                                    Repeater::make('accessorials')
                                        ->itemLabel(fn(array $state): ?string => ($state['included'] ?? false ? '🟢 ' : '') . (Accessorial::find($state['accessorial_id'])?->name ?? 'Accessorial'))
                                        ->schema([
                                            Toggle::make('included')->label('Include'),
                                            Hidden::make('accessorial_id'),
                                            TextInput::make('amount')
                                                ->numeric()
                                                ->visible(fn($get) => true), // Shown for all types
                                            TextInput::make('min')
                                                ->numeric()
                                                ->visible(fn($get) => in_array(optional(Accessorial::find($get('accessorial_id')))->type, ['transport_based', 'fuel_based'])),
                                            TextInput::make('max')
                                                ->numeric()
                                                ->visible(fn($get) => in_array(optional(Accessorial::find($get('accessorial_id')))->type,  ['transport_based', 'fuel_based'])),
                                            TextInput::make('free_time')
                                                ->numeric()
                                                ->visible(fn($get) => in_array(optional(Accessorial::find($get('accessorial_id')))->type, ['time_based', 'transport_based'])),
                                            TextInput::make('base_amount')
                                                ->numeric()
                                                ->visible(fn($get) => in_array(optional(Accessorial::find($get('accessorial_id')))->type, ['time_based'])),
                                            TextInput::make('product_type')
                                                ->visible(fn($get) => optional(Accessorial::find($get('accessorial_id')))->type === 'product_base'),
                                        ])
                                        ->columns(4)
                                        ->collapsible()
                                        ->collapsed()
                                        ->reorderable(false)
                                        ->addable(false)      // Prevent adding new items
                                        ->deletable(false) // Prevent deleting items
                                ])->collapsible()
                                ->extraAttributes([
                                    'style' => 'border-top: 2px solid #fcb410; border-radius: 0;',
                                ]),
                        ])->columnSpan([
                            'default' => 12,
                            'lg' => 8,
                        ]),
                        Grid::make(1)->schema([
                            \Filament\Schemas\Components\Section::make('Emails & Notifications')
                                ->schema([
                                    TagsInput::make('billing_email')
                                        ->label('Billing Emails')
                                        ->placeholder('Type and press Enter'),
                                    TagsInput::make('pod_email')
                                        ->label('POD Emails')
                                        ->placeholder('Type and press Enter'),
                                    TagsInput::make('status_update_email')
                                        ->label('Status Update Emails')
                                        ->placeholder('Type and press Enter'),
                                    Select::make('notification_preferences')
                                        ->multiple()
                                        ->options([
                                            'arrived_pickup' => 'Arrived at Pickup',
                                            'picked_up' => 'Picked Up',
                                            'departed_pickup' => 'Departed Pickup',
                                            'arrived_delivery' => 'Arrived at Delivery',
                                            'delivered' => 'Delivered',
                                            'departed_delivery' => 'Departed from Delivery',
                                        ]),
                                ])->extraAttributes([
                                    'style' => 'border-top: 2px solid #fcb410; border-radius: 0;',
                                ]),
                            \Filament\Schemas\Components\Section::make('Flags')
                                ->schema([
                                    Toggle::make('account_status')->label('Account Active'),
                                    Toggle::make('mandatory_reference_number'),
                                    Toggle::make('summary_invoice'),
                                    Toggle::make('no_tax'),
                                    Toggle::make('receive_status_update'),
                                    Toggle::make('include_pod_with_invoice')->label('Include BOL/POD Copies with Invoice Emails'),
                                ])->extraAttributes([
                                    'style' => 'border-top: 2px solid #fcb410; border-radius: 0;',
                                ]),
                            \Filament\Schemas\Components\Section::make('Fuel Surcharges')
                                ->schema([
                                    TextInput::make('fuel_surcharges')->label('Fuel LTL')->numeric()->required(),
                                    TextInput::make('fuel_surcharges_ftl')->label('Fuel FTL')->numeric()->required(),
                                    Toggle::make('fuel_surcharges_other')->label('Fuel LTL other')->reactive(),
                                    TextInput::make('fuel_surcharges_other_value')->label('Fuel LTL other value')->numeric()
                                        ->visible(fn($get) => $get('fuel_surcharges_other')),
                                    Toggle::make('fuel_surcharges_other_ftl')->label('Fuel FTL other')->reactive(),
                                    TextInput::make('fuel_surcharges_other_value_ftl')->label('Fuel FTL other value')->numeric()
                                        ->visible(fn($get) => $get('fuel_surcharges_other_ftl')),
                                ])->columns(2)->extraAttributes([
                                    'style' => 'border-top: 2px solid #fcb410; border-radius: 0;',
                                ]),
                            \Filament\Schemas\Components\Section::make('Vehicle Type Rates')
                                ->description(
                                    fn($get) =>
                                    collect($get('vehicle_types'))->contains('included', true)
                                        ? 'Custom vehicle rates applied'
                                        : 'Enable and override vehicle type base rates per customer'
                                )
                                ->schema([
                                    Repeater::make('vehicle_types')
                                        ->itemLabel(fn(array $state): ?string => ($state['included'] ?? false ? '🟢 ' : '') . (VehicleType::find($state['vehicle_type_id'])?->name ?? 'Vehicle Type'))
                                        ->schema([
                                            Toggle::make('included')->label('Include'),
                                            Hidden::make('vehicle_type_id'),
                                            TextInput::make('rate')->numeric()->label('Custom Rate'),
                                        ])
                                        ->columns(2)
                                        ->collapsible()
                                        ->collapsed()
                                        ->reorderable(false)
                                        ->addable(false)
                                        ->deletable(false)
                                ])
                                ->collapsible()
                                ->extraAttributes([
                                    'style' => 'border-top: 2px solid #fcb410; border-radius: 0;',
                                ]),
                        ])->columnSpan([
                            'default' => 12,
                            'lg' => 4,
                        ]),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')->label('Name')->searchable()->sortable(),
                TextColumn::make('account_number')->label('Account #')->searchable()->sortable(),

                TextColumn::make('telephone_number')->label('Phone')->searchable()->sortable(),

                TextColumn::make('invoicing')
                    ->label('Invoicing')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'daily' => 'info',
                        'weekly' => 'success',
                        'monthly' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('language')
                    ->label('Language')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'english' => 'English',
                        'french' => 'French',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'english' => 'primary',
                        'french' => 'pink',
                        default => 'gray',
                    }),


                IconColumn::make('account_status')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-m-check-badge')
                    ->falseIcon('heroicon-m-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),

            ])
            ->filters([
                SelectFilter::make('province')
                    ->label('Province')
                    ->options(Customer::query()->distinct()->pluck('province', 'province')->filter()->toArray()),
                SelectFilter::make('account_status')
                    ->label('Account Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
            ])
            ->defaultSort('full_name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RateSheetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
            // Add a custom import page (or action) for rate sheet import
            // Example: 'import' => Pages\ImportRateSheet::route('/import'), // Uncomment if you have a custom page
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['full_name', 'account_number'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->full_name} - {$record->account_number}";
    }
}
