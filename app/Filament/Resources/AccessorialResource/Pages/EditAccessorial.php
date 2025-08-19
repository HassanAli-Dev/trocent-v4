<?php

namespace App\Filament\Resources\AccessorialResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\AccessorialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;



class EditAccessorial extends EditRecord
{
    protected static string $resource = AccessorialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }


}
