<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use Filament\Actions\DeleteAction;
use App\Models\VehicleType;
use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Accessorial;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\RateSheetImport;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }



    public function mount($record): void
    {
        parent::mount($record);

        $this->form->fill([
            ...$this->form->getState(), // retain existing state
            'accessorials' => Accessorial::with(['customers' => fn ($q) => $q->where('customer_id', $this->record->id)])
                ->get()
                ->sortBy(fn ($a) => [
                    $a->customers->isNotEmpty() ? 0 : 1,   // active first
                    strtolower($a->name),                  // alphabetical within each group
                ])
                ->values()
                ->map(function ($accessorial) {
                    $pivot = $this->record->accessorials->firstWhere('id', $accessorial->id)?->pivot;

                    return [
                        'accessorial_id' => $accessorial->id,
                        'included' => !!$pivot,
                        'amount' => $pivot?->amount ?? $accessorial->amount,
                        'min_amount' => in_array($accessorial->type, ['transport_based']) ? ($pivot?->min ?? $accessorial->min) : null,
                        'max_amount' => in_array($accessorial->type, ['transport_based']) ? ($pivot?->max ?? $accessorial->max) : null,
                        'free_time' => in_array($accessorial->type, ['time_based', 'transport_based']) ? ($pivot?->free_time ?? $accessorial->free_time) : null,
                        'base_amount' => $accessorial->type === 'time_based' ? ($pivot?->base_amount ?? $accessorial->base_amount) : null,
                        'amount_type' => $accessorial->type === 'transport_based' ? ($pivot?->amount_type ?? $accessorial->amount_type) : null,
                        'product_type' => $accessorial->type === 'product_base' ? ($pivot?->product_type ?? $accessorial->product_type) : null,
                        'time_unit' => in_array($accessorial->type, ['time_based', 'transport_based']) ? ($pivot?->time_unit ?? $accessorial->time_unit) : null,
                    ];
                })->toArray(),
            'vehicle_types' => VehicleType::with(['customers' => fn ($q) => $q->where('customer_id', $this->record->id)])
                ->get()
                ->sortBy(fn ($v) => [
                    $v->customers->isNotEmpty() ? 0 : 1,
                    strtolower($v->name),
                ])
                ->values()
                ->map(function ($vehicleType) {
                    $pivot = $this->record->vehicleTypes->firstWhere('id', $vehicleType->id)?->pivot;

                    return [
                        'vehicle_type_id' => $vehicleType->id,
                        'included' => !!$pivot,
                        'rate' => $pivot?->rate ?? $vehicleType->rate,
                    ];
                })->toArray(),
        ]);
    }


    protected function afterSave(): void
    {
        $accessorials = collect($this->form->getState()['accessorials'])
            ->filter(fn($item) => !empty($item['included']) && isset($item['accessorial_id']))
            ->mapWithKeys(function ($item) {
                return [
                    $item['accessorial_id'] => collect($item)->only([
                        'amount',
                        'min',
                        'max',
                        'free_time',
                        'base_amount',
                        'product_type',
                    ])->filter(fn($val) => $val !== null)->toArray(),
                ];
            });

        $this->record->accessorials()->sync($accessorials);

        $vehicleTypes = collect($this->form->getState()['vehicle_types'])
            ->filter(fn ($item) => !empty($item['included']) && isset($item['vehicle_type_id']))
            ->mapWithKeys(function ($item) {
                return [
                    $item['vehicle_type_id'] => collect($item)->only([
                        'rate',
                    ])->filter(fn ($val) => $val !== null)->toArray()
                ];
            });

        $this->record->vehicleTypes()->sync($vehicleTypes);
    }


}
