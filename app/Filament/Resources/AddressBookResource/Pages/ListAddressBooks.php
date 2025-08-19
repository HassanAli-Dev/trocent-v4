<?php

namespace App\Filament\Resources\AddressBookResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\AddressBookResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAddressBooks extends ListRecords
{
    protected static string $resource = AddressBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->createAnother(false),
        ];
    }

    public function getTitle(): string
    {
        return 'Address Book';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You can add widgets here if needed
        ];
    }
}