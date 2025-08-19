<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Actions\CreateAction;
use Filament\Support\Enums\Width;
use Filament\Actions\EditAction;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'staff' => Tab::make('Staff')
                ->modifyQueryUsing(fn(Builder $query) =>  $query->where('type', 'admin')),
            'drivers' => Tab::make('Drivers')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'driver')),
            'customers' => Tab::make('Customers')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'customer')),
        ];
    }



    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()

                ->modalWidth(Width::FourExtraLarge)
                ->schema(UserResource::getFormSchema())

        ];
    }

    protected function getTableActions(): array
    {
        return [
            EditAction::make('edit')
                ->slideOver()

                ->schema(UserResource::getFormSchema())
                ->modalWidth(UserResource::FourExtraLarge),
        ];
    }
}
