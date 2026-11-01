<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GroupSummariesServiceProvider extends PackageServiceProvider
{
    public const string PACKAGE = 'asignua/filament-group-summaries';

    public const string STYLESHEET = 'filament-group-summaries';

    public static string $name = 'filament-group-summaries';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make(self::STYLESHEET, __DIR__.'/../resources/dist/group-summaries.css')->loadedOnRequest(),
            // Fetched by Alpine's x-load, only on a page that draws a top summary row.
            AlpineComponent::make('group-summaries-top', __DIR__.'/../resources/dist/group-summaries-top.js'),
        ], self::PACKAGE);

        Livewire::componentHook(ApplyDefaultGroupDirection::class);
    }
}
