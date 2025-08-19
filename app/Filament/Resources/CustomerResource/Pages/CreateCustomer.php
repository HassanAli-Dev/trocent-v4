<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Models\VehicleType;
use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Accessorial;
use Illuminate\Support\Facades\Log;
use Filament\Actions\Action;


class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            'accessorials' => Accessorial::all()->map(function ($accessorial) {
                return [
                    'accessorial_id' => $accessorial->id,
                    'accessorial_name' => $accessorial->name,
                    'included' => false,
                    'amount' => $accessorial->amount,
                    'min' => in_array($accessorial->type, ['transport_based']) ? $accessorial->min : null,
                    'max' => in_array($accessorial->type, ['transport_based']) ? $accessorial->max : null,
                    'free_time' => in_array($accessorial->type, ['time_based', 'transport_based']) ? $accessorial->free_time : null,
                    'base_amount' => $accessorial->type === 'time_based' ? $accessorial->base_amount : null,
                    'amount_type' => $accessorial->type === 'transport_based' ? $accessorial->amount_type : null,
                    'product_type' => $accessorial->type === 'product_base' ? $accessorial->product_type : null,
                    'time_unit' => in_array($accessorial->type, ['time_based', 'transport_based']) ? $accessorial->time_unit : null,
                ];
            })->toArray(),
            'vehicle_types' => VehicleType::all()->map(function ($vehicleType) {
                return [
                    'vehicle_type_id' => $vehicleType->id,
                    'included' => false,
                    'rate' => $vehicleType->rate,
                ];
            })->toArray(),
        ]);
    }

    public function afterCreate(): void
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
            ->filter(fn($item) => !empty($item['included']) && isset($item['vehicle_type_id']))
            ->mapWithKeys(function ($item) {
                return [
                    $item['vehicle_type_id'] => collect($item)->only([
                        'rate',
                    ])->filter(fn($val) => $val !== null)->toArray()
                ];
            });

        $this->record->vehicleTypes()->sync($vehicleTypes);
    }


}
