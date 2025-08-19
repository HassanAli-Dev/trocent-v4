<?php

namespace App\Filament\Resources\RateSheetResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\RateSheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRateSheet extends EditRecord
{
    protected static string $resource = RateSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
