<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\AccessorialResource\Pages\ListAccessorials;
use Illuminate\Database\Eloquent\Model;

use App\Filament\Resources\AccessorialResource\Pages;
use App\Models\Accessorial;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\{Grid, TextInput, Select, Toggle};
use Filament\Tables\Columns\{TextColumn, BadgeColumn};

class AccessorialResource extends Resource
{
    protected static ?string $model = Accessorial::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::getFormSchema());
    }

    public static function getFormSchema(): array
    {
        return [
            \Filament\Schemas\Components\Grid::make(1)
                ->schema([
                    TextInput::make('name')->required()->autofocus(),

                    Select::make('type')
                        ->options([
                            'fixed_price' => 'Fixed Price',
                            'fuel_based' => 'Fuel Based',
                            'package_based' => 'Package Based',
                            'product_base' => 'Product Base',
                            'time_based' => 'Time Based',
                            'transport_based' => 'Transport Based',
                        ])
                        ->required()
                        ->reactive(),



                    TextInput::make('amount')
                        ->label(fn($get) => $get('type') === 'fixed_price' ? 'Charge Amount' : 'Amount')
                        ->numeric()
                        ->visible(fn($get) => in_array($get('type'), ['fixed_price', 'time_based', 'transport_based', 'product_base', 'fuel_based', 'package_based'])),

                    TextInput::make('free_time')
                        ->numeric()
                        ->visible(fn($get) => in_array($get('type'), ['time_based', 'transport_based'])),

                    Select::make('time_unit')
                        ->options(['minute' => 'Minute', 'hour' => 'Hour'])
                        ->visible(fn($get) => in_array($get('type'), ['time_based', 'transport_based'])),

                    TextInput::make('base_amount')
                        ->label('Base Amount')
                        ->numeric()
                        ->visible(fn($get) => in_array($get('type'), ['time_based'])),

                    Select::make('amount_type')
                        ->options(['fixed' => 'Fixed', 'percentage' => 'Percentage'])
                        ->visible(fn($get) => in_array($get('type'), ['transport_based', 'fuel_based'])),

                    TextInput::make('min')
                        ->numeric()
                        ->visible(fn($get) => in_array($get('type'), ['transport_based', 'fuel_based'])),


                    TextInput::make('max')
                        ->numeric()
                        ->visible(fn($get) => in_array($get('type'), ['transport_based', 'fuel_based'])),

                    Select::make('product_type')
                        ->options([
                            'cartoon' => 'Cartoon',
                            'skid' => 'Skid',
                            'box' => 'Box',
                            'pallet' => 'Pallet',
                        ])
                        ->visible(fn($get) => $get('type') === 'product_base'),

                    // Additional fields for fuel_based and package_based
                    // Fuel based: base_amount already above (added to both time_based and fuel_based)
                    Select::make('package_type')
                        ->options([
                            'envelope' => 'Envelope',
                            'box' => 'Box',
                            'tube' => 'Tube',
                            'crate' => 'Crate',
                            'carton' => 'Carton',
                            'skid' => 'Skid',
                            'pallet' => 'Pallet',
                        ])
                        ->visible(fn($get) => $get('type') === 'package_based'),

                    Toggle::make('driver_only') ->label('Visible to Driver'),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->color(fn($state) => match ($state) {
                        'fixed_price' => 'primary',
                        'time_based' => 'info',
                        'transport_based' => 'success',
                        'product_base' => 'warning',
                        'fuel_based' => 'danger',
                        'package_based' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('driver_only')
                    ->label('Visible to Driver')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                    ->color(fn($state) => $state ? 'success' : 'secondary')->sortable(),

                TextColumn::make('amount')
                    ->numeric()
                    ->sortable()
                    ->label('Amount'),
            ])

            ->recordActions([

                Action::make('delete')
                    ->label('Delete')
                    ->action(function (Accessorial $record) {
                        $record->delete();
                    })
                    ->requiresConfirmation()
                    ->color('danger'),
                EditAction::make()->slideOver(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => ListAccessorials::route('/'),
            //'create' => Pages\CreateAccessorial::route('/create'),
            //'edit' => Pages\EditAccessorial::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'type'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->name} - {$record->type}";
    }
}
