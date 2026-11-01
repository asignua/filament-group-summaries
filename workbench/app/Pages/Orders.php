<?php

declare(strict_types=1);

namespace Workbench\App\Pages;

use Asignua\FilamentGroupSummaries\SummaryGroup;
use Asignua\FilamentGroupSummaries\SummaryPosition;
use Filament\Actions\BulkAction;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Workbench\App\Models\Order;

/**
 * Hand-testing page: /admin/orders?mode=top|both|color|header (default: top + color).
 */
class Orders extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'workbench::orders-page';

    protected static ?string $slug = 'orders';

    public function table(Table $table): Table
    {
        $mode = request('mode', 'top');

        $group = SummaryGroup::make('status')->collapsible();

        if (in_array($mode, ['top', 'both'], true)) {
            $group->position($mode === 'top' ? SummaryPosition::Top : SummaryPosition::Both);
        }

        if ($mode === 'header') {
            $group->summariesInHeader()->hideTrailingSummary();
        }

        if ($mode !== 'header') {
            $group->color(fn (string $key): ?string => match ($key) {
                'paid' => 'success',
                'open' => 'warning',
                'void' => 'danger',
                'refunded' => 'info',
                default => null,
            });
        }

        return $table
            ->query(Order::query())
            ->columns([
                TextColumn::make('reference'),
                TextColumn::make('amount')->numeric()->alignEnd()->summarize([Sum::make()->label('Total'), Average::make()->label('Avg')]),
                TextColumn::make('quantity')->alignEnd()->summarize(Count::make()->label('Orders')),
            ])
            ->groups([$group])
            ->defaultGroup($group)
            ->toolbarActions([BulkAction::make('noop')->action(fn () => null)])
            ->paginated(false);
    }
}
