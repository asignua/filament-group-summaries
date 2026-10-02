<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries\Tests;

use Asignua\FilamentGroupSummaries\SummaryGroup;
use Filament\Tables\Table;
use Livewire\Livewire;
use Workbench\App\OrdersTable;

class GroupOptionsTest extends TestCase
{
    public function test_hide_trailing_summary_emits_the_marker_only_when_asked(): void
    {
        $this->seedOrders();

        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->defaultGroup($group->hideTrailingSummary());
        $this->assertStringContainsString('data-fi-gs="hide-trailing"', Livewire::test(OrdersTable::class)->html());

        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->defaultGroup($group->hideTrailingSummary(false));
        $this->assertStringNotContainsString('data-fi-gs', Livewire::test(OrdersTable::class)->html());
    }

    public function test_the_stylesheet_hides_only_group_rows_and_is_rtl_safe(): void
    {
        $css = (string) file_get_contents(__DIR__.'/../resources/dist/group-summaries.css');

        $this->assertStringContainsString("[data-fi-gs~='hide-trailing']", $css);
        $this->assertStringContainsString('> tbody > tr.fi-ta-summary-row', $css);
        // Page / all-records totals live in the same <tbody>, after the summary header row: they must stay visible.
        $this->assertStringContainsString("> tbody > tr.fi-ta-summary-header-row ~ tr.fi-ta-summary-row {\n    display: table-row;", $css);
        $this->assertDoesNotMatchRegularExpression('/(?<![-\w])(left|right|margin-left|margin-right|padding-left|padding-right)\s*:/', $css);
        $this->assertSame($css, (string) file_get_contents(__DIR__.'/../resources/css/group-summaries.css'));
    }

    public function test_default_direction_is_normalised(): void
    {
        $this->assertNull(SummaryGroup::make('status')->getDefaultDirection());
        $this->assertSame('desc', SummaryGroup::make('status')->defaultDirection('DESC')->getDefaultDirection());
        $this->assertNull(SummaryGroup::make('status')->defaultDirection('sideways')->getDefaultDirection());
    }

    public function test_default_group_uses_the_group_direction(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $group->defaultDirection('desc')->makeDefaultOn($table);

        $component = Livewire::test(OrdersTable::class);
        $statuses = $component->instance()->getTableRecords()->pluck('status')->unique()->values()->all();

        $this->assertSame(['void', 'paid', 'open'], $statuses);
    }

    public function test_picking_a_group_applies_its_default_direction_once(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->groups([$group->defaultDirection('desc')]);

        $component = Livewire::test(OrdersTable::class)->set('tableGrouping', 'status:asc');
        $component->assertSet('tableGrouping', 'status:desc');
        $this->assertSame(['void', 'paid', 'open'], $component->instance()->getTableRecords()->pluck('status')->unique()->values()->all());

        // The user flips the direction inside the SAME group: that choice is respected.
        $component->set('tableGrouping', 'status:asc')->assertSet('tableGrouping', 'status:asc');
    }

    public function test_groups_without_a_default_direction_are_left_alone(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->groups([$group]);

        Livewire::test(OrdersTable::class)->set('tableGrouping', 'status:asc')->assertSet('tableGrouping', 'status:asc');
    }
}
