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
use Livewire\Attributes\Url;
use Workbench\App\Models\Order;

/**
 * Hand-testing page: /admin/orders?mode=top|both|color|header&hide=first|last (default: top + color).
 */
class Orders extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'workbench::orders-page';

    protected static ?string $slug = 'orders';

    // #[Url]: plain request() reads are lost on Livewire update requests (the mode reset after one click).
    #[Url]
    public string $mode = 'top';

    #[Url]
    public ?string $hide = null;

    public function table(Table $table): Table
    {
        $mode = $this->mode;

        $group = SummaryGroup::make('status')->collapsible();

        if (in_array($mode, ['top', 'both'], true)) {
            $group->position($mode === 'top' ? SummaryPosition::Top : SummaryPosition::Both);
        }

        if ($mode === 'header') {
            $group->summariesInHeader()->hideTrailingSummary();
        }

        if ($mode !== 'header') {
            $group->color(fn (?string $key): ?string => match ($key) {
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
                TextColumn::make('reference')->visibleFrom($this->hide === 'first' ? 'xl' : null),
                TextColumn::make('amount')->numeric()->alignEnd()->summarize([Sum::make()->label('Total'), Average::make()->label('Avg')]),
                TextColumn::make('quantity')->alignEnd()->visibleFrom($this->hide === 'last' ? 'xl' : null)->summarize(Count::make()->label('Orders')),
            ])
            ->groups([$group])
            ->defaultGroup($group)
            ->toolbarActions([BulkAction::make('noop')->action(fn () => null)])
            ->paginated(false);
    }
}
