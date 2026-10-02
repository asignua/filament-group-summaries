<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;

class GroupSummariesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'asignua-group-summaries';
    }

    public function register(Panel $panel): void
    {
        // After the panel's theme, so the header strip is not overridden by the theme's summary styles.
        $panel->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => '<link rel="stylesheet" href="'
            .e(FilamentAsset::getStyleHref(GroupSummariesServiceProvider::STYLESHEET, GroupSummariesServiceProvider::PACKAGE)).'" />');
    }

    public function boot(Panel $panel): void {}
}
