<?php

declare(strict_types=1);

namespace Workbench\App;

use Asignua\FilamentGroupSummaries\SummaryGroup;
use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Range;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Workbench\App\Models\Order;

/**
 * A plain Livewire table whose grouping is configured by the test through `$configure`.
 */
class OrdersTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var Closure(Table, SummaryGroup): Table|null */
    public static ?Closure $configure = null;

    public function table(Table $table): Table
    {
        $group = SummaryGroup::make('status')->collapsible();

        $table
            ->query(Order::query())
            ->columns([
                TextColumn::make('reference'),
                TextColumn::make('status'),
                TextColumn::make('amount')->summarize([
                    Sum::make()->label('Total'),
                    Average::make()->label('Avg'),
                    Range::make()->label('Range'),
                ]),
                TextColumn::make('quantity')->summarize(Count::make()->label('Orders')),
            ])
            ->filters([Filter::make('big')->query(fn (Builder $query): Builder => $query->where('amount', '>=', 100))])
            ->groups([$group]);

        $configure = static::$configure;

        return $configure === null ? $table : $configure($table, $group);
    }

    public function render(): View
    {
        return view('workbench::orders-table');
    }
}
