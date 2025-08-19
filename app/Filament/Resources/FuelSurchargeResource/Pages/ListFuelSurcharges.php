<?php

namespace App\Filament\Resources\FuelSurchargeResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Actions\EditAction;
use App\Filament\Resources\FuelSurchargeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;

class ListFuelSurcharges extends ListRecords
{
    protected static string $resource = FuelSurchargeResource::class;



    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()

                ->modalWidth(Width::FourExtraLarge)
                ->schema(FuelSurchargeResource::getFormSchema())

        ];
    }

    protected function getTableActions(): array
    {
        return [
            EditAction::make('edit')
                ->slideOver()
                ->modalHeading('Edit Accessorial')
                ->schema(FuelSurchargeResource::getFormSchema())
                ->modalWidth(FuelSurchargeResource::FourExtraLarge),
        ];
    }
}
