<?php

namespace App\Filament\Resources\RateSheetResource\Pages;

use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\RateSheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;



class ListRateSheets extends ListRecords
{
    protected static string $resource = RateSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }


    protected function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('meta');
    }
}
