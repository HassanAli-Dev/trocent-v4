<?php

namespace App\Filament\Resources\InterlinerResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\InterlinerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInterliner extends EditRecord
{
    protected static string $resource = InterlinerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
