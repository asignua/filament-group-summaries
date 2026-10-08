<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries\Tests;

use Asignua\FilamentGroupSummaries\SummaryGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Values;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Workbench\App\OrdersTable;

class SummariesInHeaderTest extends TestCase
{
    /**
     * The text of one group's header description, tags stripped and whitespace collapsed.
     */
    private function headerOf(string $html, string $status, bool $raw = false): string
    {
        $pattern = '/fi-ta-group-heading[^>]*>(?:(?!<\/h2>).)*\b'.$status.'\s*<\/h2>(.*?)<\/p>/s';
        $this->assertSame(1, preg_match($pattern, $html, $matches), "No header for [{$status}] in the HTML.");

        if ($raw) {
            return $matches[1];
        }

        return trim(html_entity_decode((string) preg_replace('/\s+/', ' ', strip_tags($matches[1]))));
    }

    private function inHeader(): void
    {
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->defaultGroup($group->summariesInHeader());
    }

    public function test_header_shows_per_group_sum_average_range_and_count(): void
    {
        $this->seedOrders();
        $this->inHeader();

        $html = Livewire::test(OrdersTable::class)->html();

        $paid = $this->headerOf($html, 'paid');
        $this->assertStringContainsString('Total 400', $paid);
        $this->assertStringContainsString('Avg 200', $paid);
        $this->assertStringContainsString('Range 100 - 300', $paid);
        $this->assertStringContainsString('Orders 2', $paid);

        $open = $this->headerOf($html, 'open');
        $this->assertStringContainsString('Total 450', $open);
        $this->assertStringContainsString('Orders 3', $open);

        $void = $this->headerOf($html, 'void');
        $this->assertStringContainsString('Total 10', $void);
    }

    public function test_header_summaries_respect_filters(): void
    {
        $this->seedOrders();
        $this->inHeader();

        $html = Livewire::test(OrdersTable::class)
            ->set('tableFilters.big.isActive', true)
            ->html();

        // Only amounts >= 100: paid keeps both (400), open keeps 150 + 250 (400), void disappears.
        $this->assertStringContainsString('Total 400', $this->headerOf($html, 'paid'));
        $this->assertStringContainsString('Total 400', $this->headerOf($html, 'open'));
        $this->assertStringContainsString('Orders 2', $this->headerOf($html, 'open'));
        $this->assertStringNotContainsString('Status: void', $html);
    }

    public function test_aggregate_runs_once_for_all_group_headers(): void
    {
        $this->seedOrders();

        $aggregates = function (): int {
            $count = 0;
            DB::listen(function ($query) use (&$count): void {
                if (str_contains(strtolower($query->sql), 'sum(') && str_contains(strtolower($query->sql), 'group by')) {
                    $count++;
                }
            });
            Livewire::test(OrdersTable::class);

            return $count;
        };

        // Filament's own grouped aggregate for its summary rows: the baseline.
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->defaultGroup($group);
        $baseline = $aggregates();

        // Three group headers add exactly ONE more aggregate, not three.
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->defaultGroup($group->summariesInHeader());
        $this->assertSame($baseline + 1, $aggregates());
    }

    public function test_header_is_rendered_for_collapsed_groups_and_stays_in_the_header(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->defaultGroup($group->summariesInHeader())
            ->collapsedGroupsByDefault();

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertStringContainsString('areGroupsCollapsedByDefault: true', $html);
        $this->assertStringContainsString('Total 400', $this->headerOf($html, 'paid'));
    }

    public function test_summary_columns_and_column_labels_options(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->defaultGroup($group->summariesInHeader()->summaryColumns(['quantity'])->columnLabels(false));

        $paid = $this->headerOf(Livewire::test(OrdersTable::class)->html(), 'paid');

        $this->assertStringContainsString('Orders', $paid);
        $this->assertStringNotContainsString('Total', $paid);
        $this->assertStringNotContainsString('Amount', $paid);
    }

    public function test_description_is_kept_and_escaped(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->defaultGroup($group->summariesInHeader()->getDescriptionFromRecordUsing(fn (): string => '<b>x</b> & y'));

        $paid = $this->headerOf(Livewire::test(OrdersTable::class)->html(), 'paid');

        $this->assertStringContainsString('<b>x</b> & y', $paid);
        $this->assertStringContainsString('Total 400', $paid);
    }

    public function test_values_summarizer_does_not_emit_block_tags_into_the_paragraph(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->columns([
                TextColumn::make('reference')->summarize(Values::make()->label('Refs')),
                TextColumn::make('status'),
            ])
            ->defaultGroup($group->summariesInHeader());

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertStringContainsString('Refs R1, R2', $this->headerOf($html, 'paid'));
        $raw = $this->headerOf($html, 'paid', raw: true);
        $this->assertStringNotContainsString('<ul', $raw);
        $this->assertStringNotContainsString('<div', $raw);
    }

    public function test_values_summarizer_accepts_a_collection_htmlable_values_and_extra_attributes(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->columns([
                TextColumn::make('reference')->summarize(
                    Values::make()
                        ->label('Refs')
                        ->using(fn ($query) => $query->orderBy('reference')->pluck('reference'))
                        ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('<em>'.e($state).'</em>'))
                        ->extraAttributes(['data-test' => 'refs']),
                ),
                TextColumn::make('status'),
            ])
            ->defaultGroup($group->summariesInHeader());

        $raw = $this->headerOf(Livewire::test(OrdersTable::class)->html(), 'paid', raw: true);

        $this->assertStringContainsString('<em>R1</em>, <em>R2</em>', $raw);
        $this->assertStringContainsString('data-test="refs"', $raw);
    }

    public function test_icon_count_summarizer_does_not_emit_block_tags_into_the_paragraph(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->columns([
                TextColumn::make('reference'),
                IconColumn::make('status')
                    ->icon(fn (string $state): string => $state === 'paid' ? 'heroicon-o-check-circle' : 'heroicon-o-clock')
                    ->summarize(Count::make()->icons()->label('States')),
            ])
            ->defaultGroup($group->summariesInHeader()->hideTrailingSummary());

        $raw = $this->headerOf(Livewire::test(OrdersTable::class)->html(), 'paid', raw: true);

        $this->assertStringContainsString('fi-ta-icon-count-summary', $raw);
        $this->assertStringContainsString('<svg', $raw);
        $this->assertStringContainsString('data-fi-gs="hide-trailing"', $raw, 'The marker must still be inside the description paragraph.');

        foreach (['<div', '<ul', '<li', '</div>', '</ul>', '</li>'] as $tag) {
            $this->assertStringNotContainsString($tag, $raw);
        }
    }

    public function test_custom_view_summarizer_renders_its_view_flattened_in_the_header(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->columns([
                TextColumn::make('reference'),
                TextColumn::make('status'),
                TextColumn::make('amount')->summarize(Sum::make()->label('Total')->view('workbench::custom-summary')),
            ])
            ->defaultGroup($group->summariesInHeader());

        $raw = $this->headerOf(Livewire::test(OrdersTable::class)->html(), 'paid', raw: true);

        $this->assertStringContainsString('CUSTOM-VIEW', $raw);
        $this->assertStringContainsString('custom-summary', $raw);

        foreach (['<div', '<pre', '<blockquote', '<figure', '<hr', '</pre>'] as $tag) {
            $this->assertStringNotContainsString($tag, $raw);
        }
    }

    public function test_values_summarizer_with_a_custom_view_is_not_flattened_into_a_list(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->columns([
                TextColumn::make('reference'),
                TextColumn::make('status'),
                TextColumn::make('amount')->summarize(Values::make()->label('Vals')->view('workbench::custom-summary')),
            ])
            ->defaultGroup($group->summariesInHeader());

        $raw = $this->headerOf(Livewire::test(OrdersTable::class)->html(), 'paid', raw: true);

        $this->assertStringContainsString('CUSTOM-VIEW', $raw);
        $this->assertStringNotContainsString('fi-ta-values-summary', $raw);
    }

    public function test_plain_group_and_summary_off_leave_the_header_untouched(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->defaultGroup($group);

        $html = Livewire::test(OrdersTable::class)->html();

        $this->assertStringNotContainsString('fi-gs-strip', $html);
        $this->assertStringNotContainsString('data-fi-gs', $html);
    }
}
