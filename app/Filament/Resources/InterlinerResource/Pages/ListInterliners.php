<?php

namespace App\Filament\Resources\InterlinerResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\InterlinerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInterliners extends ListRecords
{
    protected static string $resource = InterlinerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
