<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationGroup;
use App\Filament\Resources\CustomerResource\Widgets\CustomerStats;
use App\Filament\Resources\FuelSurchargeResource\Widgets\LatestFuelSurcharges;
use App\Filament\Widgets\CustomInfoWidget;



class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        Notifications::alignment(Alignment::Center);

        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->brandName('Trocent')
            ->favicon(asset('favicon-32x32.png'))
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop(true)
            ->login()
            ->colors([
                'primary' => '#f5a100',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->renderHook('panels::footer', fn() => sprintf(
                <<<HTML
                <div class="text-center text-sm text-gray-500 py-6">
                    © %d <a href="https://www.iamcorp.ca/" target="_blank" rel="noopener" class="underline hover:text-primary-600">IAM INC</a>. All rights reserved. <span class="ml-2 text-gray-400">v2.0</span>


                </div>
            HTML,
                now()->year
            ))
            ->renderHook('panels::head.end', fn() => <<<'HTML'
            <style>
                .fi-main {
                    min-height: 85vh;
                }
                span.fi-btn-label {
                    text-transform: capitalize;
                }
                .section-yellow-border {
                    border-top: 2px solid #fcb410;
                    border-radius: 0;
                }

            </style>
        HTML)
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                CustomerStats::class,
                LatestFuelSurcharges::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Customers')
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make()
                    ->label('Fleet Management')
                    ->icon('heroicon-o-truck'),

                NavigationGroup::make()
                    ->label('Settings')
                    ->icon('heroicon-o-cog'),
                NavigationGroup::make()
                    ->label('Access Management')
                    ->icon('heroicon-o-shield-check'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])

            ->profile()
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
