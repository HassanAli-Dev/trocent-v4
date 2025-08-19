<?php

namespace App\Filament\Resources\InterlinerResource\Pages;

use App\Filament\Resources\InterlinerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInterliner extends CreateRecord
{
    protected static string $resource = InterlinerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'interliner';
        return $data;
    }
}
