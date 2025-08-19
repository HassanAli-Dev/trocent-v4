<?php

namespace App\Filament\Resources\VehicleTypeResource\Pages;

use Filament\Support\Enums\Width;
use App\Filament\Resources\VehicleTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Tables\Actions\Action;

class ListVehicleTypes extends ListRecords
{
    protected static string $resource = VehicleTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()

                ->modalWidth(Width::FourExtraLarge)
                ->schema(VehicleTypeResource::getFormSchema())

        ];
    }

    protected function getTableActions(): array
    {
        return [
            EditAction::make('edit')
                ->slideOver()
                ->modalHeading('Edit Vehicle Type')
                ->schema(VehicleTypeResource::getFormSchema())
                ->modalWidth(Width::FourExtraLarge),
        ];
    }
}
