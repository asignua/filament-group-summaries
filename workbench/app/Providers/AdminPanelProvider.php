<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Asignua\FilamentGroupSummaries\GroupSummariesPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\View;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Pages\Orders;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        View::addNamespace('workbench', __DIR__.'/../../resources/views');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->plugin(GroupSummariesPlugin::make())
            ->pages([Orders::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                // Laravel 13 renamed VerifyCsrfToken to PreventRequestForgery; the string is the Laravel 12 fallback.
                class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : 'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
