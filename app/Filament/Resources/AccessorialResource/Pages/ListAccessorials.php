<?php

namespace App\Filament\Resources\AccessorialResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Actions\EditAction;
use App\Filament\Resources\AccessorialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\CreateAction;

class ListAccessorials extends ListRecords
{
    protected static string $resource = AccessorialResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Action::make('create')
    //             ->label('Add Accessorial')
    //             ->icon('heroicon-o-plus')
    //             ->slideOver()
    //             ->modalHeading('Create Accessorial')
    //             ->modalWidth(MaxWidth::FourExtraLarge)
    //             ->form([
    //                 TextInput::make('name')->required()->autofocus(),
    //                 Select::make('type')
    //                     ->options([
    //                         'fixed_price' => 'Fixed Price',
    //                         'time_based' => 'Time Based',
    //                         'transport_based' => 'Transport Based',
    //                         'product_base' => 'Product Base',
    //                         'fuel_based' => 'Fuel Based',
    //                         'package_based' => 'Package Based',
    //                     ])
    //                     ->required()
    //                     ->reactive(),
    //                 TextInput::make('amount')
    //                     ->label('Charge Amount')
    //                     ->numeric()
    //                     ->visible(fn($get) => in_array($get('type'), ['fixed_price', 'time_based', 'transport_based', 'product_base', 'fuel_based', 'package_based'])),

    //                 TextInput::make('free_time')
    //                     ->numeric()
    //                     ->visible(fn($get) => in_array($get('type'), ['time_based', 'transport_based'])),

    //                 Select::make('time_unit')
    //                     ->options(['minute' => 'Minute', 'hour' => 'Hour'])
    //                     ->visible(fn($get) => in_array($get('type'), ['time_based', 'transport_based'])),

    //                 TextInput::make('base_amount')
    //                     ->label('Base Amount')
    //                     ->numeric()
    //                     ->visible(fn($get) => in_array($get('type'), ['time_based'])),

    //                 Select::make('amount_type')
    //                     ->options(['fixed' => 'Fixed', 'percentage' => 'Percentage'])
    //                     ->visible(fn($get) => in_array($get('type'), ['transport_based', 'fuel_based'])),

    //                 TextInput::make('min')
    //                     ->numeric()
    //                     ->visible(fn($get) => in_array($get('type'), ['transport_based', 'fuel_based'])),


    //                 TextInput::make('max')
    //                     ->numeric()
    //                     ->visible(fn($get) => in_array($get('type'), ['transport_based', 'fuel_based'])),

    //                 Select::make('product_type')
    //                     ->options([
    //                         'cartoon' => 'Cartoon',
    //                         'skid' => 'Skid',
    //                         'box' => 'Box',
    //                         'pallet' => 'Pallet',
    //                     ])
    //                     ->visible(fn($get) => $get('type') === 'product_base'),

    //                 // Additional fields for fuel_based and package_based
    //                 // Fuel based: base_amount already above (added to both time_based and fuel_based)
    //                 Select::make('package_type')
    //                     ->options([
    //                         'envelope' => 'Envelope',
    //                         'box' => 'Box',
    //                         'tube' => 'Tube',
    //                         'crate' => 'Crate',
    //                     ])
    //                     ->visible(fn($get) => $get('type') === 'package_based'),

    //                 Toggle::make('driver_only'),
    //             ])
    //             ->action(function (array $data) {
    //                 \App\Models\Accessorial::create($data);
    //             }),

    //     ];
    // }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->modalHeading('Add Accessorial')
                ->modalWidth(Width::FourExtraLarge)
                ->schema(AccessorialResource::getFormSchema())

        ];
    }

    protected function getTableActions(): array
    {
        return [
            EditAction::make('edit')
                ->slideOver()
                ->modalHeading('Edit Accessorial')
                ->modalWidth(Width::FourExtraLarge),
        ];
    }
}
