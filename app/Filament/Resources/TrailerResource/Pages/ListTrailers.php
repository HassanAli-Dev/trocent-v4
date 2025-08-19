<?php

namespace App\Filament\Resources\TrailerResource\Pages;

use App\Filament\Resources\TrailerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrailers extends ListRecords
{
    protected static string $resource = TrailerResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
