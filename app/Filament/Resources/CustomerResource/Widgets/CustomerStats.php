<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Customer;

class CustomerStats extends BaseWidget
{


    protected function getStats(): array
    {
        $total = Customer::count();

        $thisWeek = Customer::where('created_at', '>=', now()->subWeek())->count();
        $lastWeek = Customer::whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->count();

        $trend = $lastWeek === 0 ? 100 : (($thisWeek - $lastWeek) / max($lastWeek, 1)) * 100;
        $trendRounded = number_format(abs($trend), 1);

        $icon = $trend > 0 ? 'heroicon-m-arrow-trending-up' : ($trend < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus');
        $color = $trend > 0 ? 'success' : ($trend < 0 ? 'danger' : 'gray');
        $prefix = $trend > 0 ? '↑' : ($trend < 0 ? '↓' : '');

        return [
            Stat::make('Total Customers', $total)
                ->description("{$prefix} {$thisWeek} new ({$trendRounded}% vs last week)")
                ->descriptionIcon($icon)
                ->color($color),

            Stat::make('Total Orders', 200)
                ->description('↑ 5 new this week') // or dynamic based on data
                ->descriptionIcon('heroicon-m-arrow-trending-up') // or down
                ->color('success'), // success, danger, warning, etc.

            Stat::make('Revenue', '21%')
                ->description('7% decrease')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
        ];
    }
}
