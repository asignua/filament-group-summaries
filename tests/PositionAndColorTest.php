<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries\Tests;

use Asignua\FilamentGroupSummaries\SummaryGroup;
use Asignua\FilamentGroupSummaries\SummaryPosition;
use Filament\Tables\Table;
use Livewire\Livewire;
use Workbench\App\OrdersTable;

class PositionAndColorTest extends TestCase
{
    private function configure(callable $callback): void
    {
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->defaultGroup($callback($group));
    }

    public function test_default_position_is_bottom_and_changes_nothing(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group);

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertSame(SummaryPosition::Bottom, SummaryGroup::make('x')->getPosition());
        $this->assertStringNotContainsString('fi-gs-top', $html);
        $this->assertStringNotContainsString('data-fi-gs="hide-trailing"', $html);
        $this->assertStringNotContainsString('fi-gs-color', $html);
    }

    public function test_top_draws_an_aligned_row_per_group_and_hides_the_trailing_one(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group->position(SummaryPosition::Top));

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertSame(3, substr_count($html, 'class="fi-gs-top"'));
        $this->assertStringContainsString('data-gs-th="fi-ta-header-cell-amount"', $html);
        $this->assertStringContainsString('data-fi-gs="hide-trailing"', $html);
        $this->assertMatchesRegularExpression('/fi-gs-top-cell fi-gs-align-start" data-gs-th="fi-ta-header-cell-amount">.*?Total.*?400/s', $html);
    }

    public function test_both_keeps_the_trailing_row(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group->position(SummaryPosition::Both));

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertStringContainsString('fi-gs-top', $html);
        $this->assertStringNotContainsString('data-fi-gs="hide-trailing"', $html);
    }

    public function test_position_accepts_a_closure(): void
    {
        $group = SummaryGroup::make('status')->position(fn (): SummaryPosition => SummaryPosition::Top);

        $this->assertSame(SummaryPosition::Top, $group->getPosition());
        $this->assertTrue($group->hidesTrailingSummary());
    }

    public function test_top_row_replaces_the_header_strip(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group->summariesInHeader()->position(SummaryPosition::Top));

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertStringContainsString('fi-gs-top', $html);
        $this->assertStringNotContainsString('fi-gs-strip', $html);
    }

    public function test_color_by_name_adds_the_tint_marker_with_filament_colour_variables(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group->color('success'));

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertSame(3, substr_count($html, 'class="fi-gs-color" aria-hidden'));
        $this->assertStringContainsString('--color-500:var(--success-500)', $html);
        $this->assertStringContainsString('--color-400:var(--success-400)', $html);
    }

    public function test_color_closure_is_resolved_per_group_value(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group->color(fn (string $key): ?string => match ($key) {
            'paid' => 'success',
            'void' => 'danger',
            default => null,
        }));

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertSame(2, substr_count($html, 'class="fi-gs-color" aria-hidden'));
        $this->assertStringContainsString('--color-500:var(--success-500)', $html);
        $this->assertStringContainsString('--color-500:var(--danger-500)', $html);
        $this->assertStringNotContainsString('--warning-', $html);
    }

    public function test_color_closure_receives_the_record(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group->color(fn ($record): ?string => $record->status === 'open' ? 'warning' : null));

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertSame(1, substr_count($html, 'class="fi-gs-color" aria-hidden'));
        $this->assertStringContainsString('--warning-500', $html);
    }

    public function test_color_array_map_by_group_key(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group->color(['paid' => 'success', 'open' => 'info']));

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertSame(2, substr_count($html, 'class="fi-gs-color" aria-hidden'));
        $this->assertStringContainsString('--info-500', $html);
    }

    public function test_color_composes_with_header_summaries(): void
    {
        $this->seedOrders();
        $this->configure(fn (SummaryGroup $group) => $group->summariesInHeader()->color('primary'));

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertStringContainsString('fi-gs-strip', $html);
        $this->assertStringContainsString('fi-gs-color', $html);
    }

    public function test_default_color_is_neutral(): void
    {
        $this->assertNull(SummaryGroup::make('status')->getColor([], 'x'));
    }
}
