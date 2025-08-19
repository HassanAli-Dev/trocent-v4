<?php

namespace App\Filament\Resources\FuelSurchargeResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\FuelSurchargeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFuelSurcharge extends EditRecord
{
    protected static string $resource = FuelSurchargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
