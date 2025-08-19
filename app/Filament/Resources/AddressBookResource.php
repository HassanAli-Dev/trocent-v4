<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\AddressBookResource\Pages\ListAddressBooks;
use Illuminate\Database\Eloquent\Model;
use App\Models\AddressBook;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\AddressBookResource\Pages;

class AddressBookResource extends Resource
{
    protected static ?string $model = AddressBook::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Address Book';

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Company/Location Name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn($state, callable $set) => $set('name', strtoupper($state))),

                    TextInput::make('contact_name')
                        ->label('Contact Person')
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn($state, callable $set) => $set('contact_name', strtoupper($state ?? ''))),

                    TextInput::make('phone')
                        ->label('Phone Number')
                        ->tel()
                        ->maxLength(20),

                    TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->maxLength(255),
                ]),

                Fieldset::make('Address Details')
                    ->columns(1)
                    ->schema([
                        TextInput::make('address')
                            ->label('Street Address')
                            ->required()
                            ->maxLength(500)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set) => $set('address', strtoupper($state))),

                        Grid::make(4)->schema([
                            TextInput::make('suite')
                                ->label('Suite/Unit')
                                ->maxLength(50)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn($state, callable $set) => $set('suite', strtoupper($state ?? ''))),

                            TextInput::make('city')
                                ->label('City')
                                ->required()
                                ->maxLength(100)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn($state, callable $set) => $set('city', strtoupper($state))),

                            Select::make('province')
                                ->label('Province')
                                ->required()
                                ->options([
                                    'AB' => 'Alberta',
                                    'BC' => 'British Columbia',
                                    'MB' => 'Manitoba',
                                    'NB' => 'New Brunswick',
                                    'NL' => 'Newfoundland and Labrador',
                                    'NS' => 'Nova Scotia',
                                    'NT' => 'Northwest Territories',
                                    'NU' => 'Nunavut',
                                    'ON' => 'Ontario',
                                    'PE' => 'Prince Edward Island',
                                    'QC' => 'Quebec',
                                    'SK' => 'Saskatchewan',
                                    'YT' => 'Yukon',
                                ])
                                ->searchable()
                                ->preload(),

                            TextInput::make('postal_code')
                                ->label('Postal Code')
                                ->required()
                                ->maxLength(10)
                                ->placeholder('A1A 1A1')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn($state, callable $set) => $set('postal_code', strtoupper($state))),
                        ]),

                        Textarea::make('special_instructions')
                            ->label('Special Instructions')
                            ->maxLength(1000)
                            ->rows(2)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set) => $set('special_instructions', strtoupper($state ?? ''))),

                        Grid::make(2)->schema([
                            TimePicker::make('operating_hours_from')
                                ->label('Operating Hours From')
                                ->format('H:i')
                                ->seconds(false),

                            TimePicker::make('operating_hours_to')
                                ->label('Operating Hours To')
                                ->format('H:i')
                                ->seconds(false),

                            Toggle::make('requires_appointment')
                                ->label('Requires Appointment')
                                ->helperText('Check if appointments are required'),

                            Toggle::make('no_waiting_time')
                                ->label('No Waiting Time Charges')
                                ->helperText('Check to exclude waiting time charges'),
                        ]),
                    ]),



            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Company/Location')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (AddressBook $record): ?string {
                        return $record->full_address;
                    }),

                TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('province')
                    ->label('Province')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),

                IconColumn::make('requires_appointment')
                    ->label('Appt')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip('Requires Appointment'),

                IconColumn::make('no_waiting_time')
                    ->label('No Wait')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip('No Waiting Time'),

                
            ])
            ->filters([
                Filter::make('requires_appointment')
                    ->label('Requires Appointment')
                    ->query(fn(Builder $query): Builder => $query->where('requires_appointment', true)),

                Filter::make('no_waiting_time')
                    ->label('No Waiting Time')
                    ->query(fn(Builder $query): Builder => $query->where('no_waiting_time', true)),

                SelectFilter::make('province')
                    ->options([
                        'AB' => 'Alberta',
                        'BC' => 'British Columbia',
                        'MB' => 'Manitoba',
                        'NB' => 'New Brunswick',
                        'NL' => 'Newfoundland and Labrador',
                        'NS' => 'Nova Scotia',
                        'NT' => 'Northwest Territories',
                        'NU' => 'Nunavut',
                        'ON' => 'Ontario',
                        'PE' => 'Prince Edward Island',
                        'QC' => 'Quebec',
                        'SK' => 'Saskatchewan',
                        'YT' => 'Yukon',
                    ])
                    ->searchable()
                    ->preload(),

                Filter::make('frequently_used')
                    ->label('Frequently Used (10+ times)')
                    ->query(fn(Builder $query): Builder => $query->where('usage_count', '>=', 10)),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->searchable()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAddressBooks::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'contact_name', 'address', 'city', 'postal_code'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Address' => $record->full_address,
            'Contact' => $record->contact_name,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
