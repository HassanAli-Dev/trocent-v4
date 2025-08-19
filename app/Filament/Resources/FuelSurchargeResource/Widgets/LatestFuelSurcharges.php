<?php

namespace App\Filament\Resources\FuelSurchargeResource\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

use App\Models\FuelSurcharge;

class LatestFuelSurcharges extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder|Relation|null
    {
        return FuelSurcharge::query()->latest()->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('ltl_surcharge')
                ->label('LTL Surcharge %')

                ->numeric()
                ->sortable(),
            TextColumn::make('ftl_surcharge')
                ->label('FTL Surcharge %')

                ->sortable(),



            TextColumn::make('created_at')
                ->label('Created At')
                ->dateTime()
                ->sortable(),
        ];
    }
}
