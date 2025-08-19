<?php

namespace App\Filament\Resources\VehicleTypeResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\VehicleTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicleType extends EditRecord
{
    protected static string $resource = VehicleTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
