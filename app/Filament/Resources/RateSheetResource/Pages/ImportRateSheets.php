<?php

namespace App\Filament\Resources\RateSheetResource\Pages;

use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Filament\Resources\RateSheetResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class ImportRateSheets extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = RateSheetResource::class;

    protected string $view = 'filament.resources.rate-sheet-resource.pages.import-rate-sheets';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            FileUpload::make('file')
                ->label('Upload Rate Sheet (.xlsx)')
                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                ->required(),
        ];
    }

    protected function getFormModel(): Model|string|null
    {
        return $this;
    }

    public function submit(): void
    {
        $file = $this->form->getState()['file'];

        // Save temporarily and trigger import (to be implemented)
        Notification::make()
            ->title('Import started')
            ->success()
            ->send();
    }
}
