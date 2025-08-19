<?php

namespace App\Filament\Resources\AccessorialResource\Pages;

use App\Filament\Resources\AccessorialResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAccessorial extends CreateRecord
{
    protected static string $resource = AccessorialResource::class;


    public static function shouldOpenInModal(): bool
    {
        return true;
    }

    public static function getModalWidth(): string
    {
        return '4xl';
    }
}
