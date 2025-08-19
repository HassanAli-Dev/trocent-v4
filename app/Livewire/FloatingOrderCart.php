<?php

namespace App\Livewire;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Enums\FontWeight;

class FloatingOrderCart extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public $formData = [];
    public $total = 0;

    protected $listeners = ['form-updated' => 'updateFormData'];

    public function mount($formData = [])
    {
        $this->formData = $formData;
        $this->calculateTotal();
    }

    public function updateFormData($formData)
    {
        $this->formData = $formData;
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $freightRate = (float) ($this->formData['freight_rate_amount'] ?? 0);
        $fuelSurcharge = (float) ($this->formData['fuel_surcharge_amount'] ?? 0);
        $freightTotal = $freightRate + $fuelSurcharge;

        $accessorials = $this->formData['customer_accessorials'] ?? [];
        $serviceCharges = $this->formData['service_charges'] ?? [];
        $accessorialTotal = 0;

        foreach ($accessorials as $accessorial) {
            if (($accessorial['is_included'] ?? false) && ($accessorial['calculated_amount'] ?? 0) > 0) {
                $accessorialTotal += $accessorial['calculated_amount'];
            }
        }

        foreach ($serviceCharges as $charge) {
            $qty = (float) ($charge['charge_qty'] ?? 0);
            $amount = (float) ($charge['charge_amount'] ?? 0);
            $accessorialTotal += $qty * $amount;
        }

        $subtotal = $freightTotal + $accessorialTotal;
        $tax = $subtotal * 0.13;
        $this->total = $subtotal + $tax;
    }

    public function viewDetailsAction(): Action
    {
        return Action::make('viewDetails')
            ->slideOver()
            ->modalHeading('Order Summary')
            ->modalDescription('Complete breakdown of your order charges')
            ->modalWidth('lg')
            ->schema([
                Section::make('Charge Breakdown')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('freight_charges')
                                    ->label('Freight Charges')
                                    ->content(function () {
                                        $freightRate = (float) ($this->formData['freight_rate_amount'] ?? 0);
                                        $fuelSurcharge = (float) ($this->formData['fuel_surcharge_amount'] ?? 0);
                                        $freightTotal = $freightRate + $fuelSurcharge;
                                        return '$' . number_format($freightTotal, 2);
                                    })
                                    ->columnSpan(1),

                                Placeholder::make('accessorial_charges')
                                    ->label('Accessorial Charges')
                                    ->content(function () {
                                        $accessorials = $this->formData['customer_accessorials'] ?? [];
                                        $serviceCharges = $this->formData['service_charges'] ?? [];
                                        $accessorialTotal = 0;

                                        foreach ($accessorials as $accessorial) {
                                            if (($accessorial['is_included'] ?? false) && ($accessorial['calculated_amount'] ?? 0) > 0) {
                                                $accessorialTotal += $accessorial['calculated_amount'];
                                            }
                                        }

                                        foreach ($serviceCharges as $charge) {
                                            $qty = (float) ($charge['charge_qty'] ?? 0);
                                            $amount = (float) ($charge['charge_amount'] ?? 0);
                                            $accessorialTotal += $qty * $amount;
                                        }

                                        return '$' . number_format($accessorialTotal, 2);
                                    })
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700']),

                Section::make('Summary')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('subtotal')
                                    ->label('Subtotal')
                                    ->content(function () {
                                        $freightRate = (float) ($this->formData['freight_rate_amount'] ?? 0);
                                        $fuelSurcharge = (float) ($this->formData['fuel_surcharge_amount'] ?? 0);
                                        $freightTotal = $freightRate + $fuelSurcharge;

                                        $accessorials = $this->formData['customer_accessorials'] ?? [];
                                        $serviceCharges = $this->formData['service_charges'] ?? [];
                                        $accessorialTotal = 0;

                                        foreach ($accessorials as $accessorial) {
                                            if (($accessorial['is_included'] ?? false) && ($accessorial['calculated_amount'] ?? 0) > 0) {
                                                $accessorialTotal += $accessorial['calculated_amount'];
                                            }
                                        }

                                        foreach ($serviceCharges as $charge) {
                                            $qty = (float) ($charge['charge_qty'] ?? 0);
                                            $amount = (float) ($charge['charge_amount'] ?? 0);
                                            $accessorialTotal += $qty * $amount;
                                        }

                                        $subtotal = $freightTotal + $accessorialTotal;
                                        return '$' . number_format($subtotal, 2);
                                    })
                                    ->extraAttributes(['class' => 'font-semibold'])
                                    ->columnSpan(1),

                                Placeholder::make('provincial_tax')
                                    ->label('Provincial Tax (0%)')
                                    ->content('$0.00')
                                    ->extraAttributes(['class' => 'text-gray-600 dark:text-gray-400'])
                                    ->columnSpan(1),

                                Placeholder::make('federal_tax')
                                    ->label('Federal Tax (13%)')
                                    ->content(function () {
                                        $freightRate = (float) ($this->formData['freight_rate_amount'] ?? 0);
                                        $fuelSurcharge = (float) ($this->formData['fuel_surcharge_amount'] ?? 0);
                                        $freightTotal = $freightRate + $fuelSurcharge;

                                        $accessorials = $this->formData['customer_accessorials'] ?? [];
                                        $serviceCharges = $this->formData['service_charges'] ?? [];
                                        $accessorialTotal = 0;

                                        foreach ($accessorials as $accessorial) {
                                            if (($accessorial['is_included'] ?? false) && ($accessorial['calculated_amount'] ?? 0) > 0) {
                                                $accessorialTotal += $accessorial['calculated_amount'];
                                            }
                                        }

                                        foreach ($serviceCharges as $charge) {
                                            $qty = (float) ($charge['charge_qty'] ?? 0);
                                            $amount = (float) ($charge['charge_amount'] ?? 0);
                                            $accessorialTotal += $qty * $amount;
                                        }

                                        $subtotal = $freightTotal + $accessorialTotal;
                                        $tax = $subtotal * 0.13;
                                        return '$' . number_format($tax, 2);
                                    })
                                    ->columnSpan(1),

                                Placeholder::make('empty_space')
                                    ->label('')
                                    ->content('')
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700']),

                Section::make('Total')
                    ->schema([
                        Placeholder::make('total')
                            ->label('Total Amount')
                            ->content('$' . number_format($this->total, 2))
                            ->extraAttributes(['class' => 'font-bold text-2xl text-primary-600 dark:text-primary-400']),

                        Placeholder::make('note')
                            ->label('')
                            ->content('All amounts in CAD • Taxes calculated automatically')
                            ->extraAttributes(['class' => 'text-sm text-gray-500 dark:text-gray-400 mt-2']),
                    ])
                    ->extraAttributes(['class' => 'bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800'])
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalCancelAction(
                fn() => Action::make('close')
                    ->label('Close')
                    ->color('gray')
                    ->outlined()
            );
    }

    public function render()
    {
        return view('livewire.floating-order-cart');
    }
}
