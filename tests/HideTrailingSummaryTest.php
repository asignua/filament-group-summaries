<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries\Tests;

use Asignua\FilamentGroupSummaries\SummaryGroup;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Livewire\Livewire;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Workbench\App\OrdersTable;

/**
 * Applies the stylesheet's hide-trailing rule to the real rendered table (CSS -> XPath) and checks
 * which summary rows it hides: every per-group row, never the page / all-records totals.
 */
class HideTrailingSummaryTest extends TestCase
{
    private const SCOPE = "table:has([data-fi-gs~='hide-trailing']) > ";

    /**
     * The `tbody > …` part of every rule under the marker scope that sets `display: none`.
     *
     * @return array<int, string>
     */
    private function hidingSelectors(): array
    {
        $css = (string) file_get_contents(__DIR__.'/../resources/dist/group-summaries.css');

        preg_match_all('/'.preg_quote(self::SCOPE, '/').'(tbody > [^{,]+?)\s*\{\s*display:\s*none;/', $css, $matches);
        $this->assertNotEmpty($matches[1], 'No hide-trailing rule found in the stylesheet.');

        return $matches[1];
    }

    /**
     * Node paths of the summary rows, and of those the stylesheet hides.
     *
     * @return array{rows: array<int, string>, hidden: array<int, string>}
     */
    private function summaryRows(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $table = $xpath->query("//table[.//*[@data-fi-gs='hide-trailing']]")?->item(0);
        $this->assertInstanceOf(DOMElement::class, $table, 'The table with the marker is missing.');

        $rows = [];

        foreach ($xpath->query("./tbody/tr[contains(concat(' ', normalize-space(@class), ' '), ' fi-ta-summary-row ')]", $table) ?: [] as $row) {
            $this->assertInstanceOf(DOMElement::class, $row);
            $rows[] = (string) $row->getNodePath();
        }

        $converter = new CssSelectorConverter;
        $hidden = [];

        foreach ($this->hidingSelectors() as $selector) {
            foreach ($xpath->query($converter->toXPath($selector), $table) ?: [] as $row) {
                $this->assertInstanceOf(DOMElement::class, $row);
                $hidden[] = (string) $row->getNodePath();
            }
        }

        return ['rows' => $rows, 'hidden' => $hidden];
    }

    public function test_single_page_table_keeps_its_total_row(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table->defaultGroup($group->hideTrailingSummary());

        ['rows' => $rows, 'hidden' => $hidden] = $this->summaryRows(Livewire::test(OrdersTable::class)->html());

        // 3 per-group rows + the all-records total (no page summary: one page only).
        $this->assertCount(4, $rows);
        $this->assertSame(array_slice($rows, 0, 3), $hidden);
        $this->assertNotContains(end($rows), $hidden);
    }

    public function test_paginated_table_keeps_page_and_all_records_totals(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->defaultGroup($group->hideTrailingSummary())
            ->paginated([5])
            ->defaultPaginationPageOption(5);

        ['rows' => $rows, 'hidden' => $hidden] = $this->summaryRows(Livewire::test(OrdersTable::class)->html());

        // Page 1: open (3) + paid (2) -> 2 per-group rows, then the page total and the all-records total.
        $this->assertCount(4, $rows);
        $this->assertSame(array_slice($rows, 0, 2), $hidden);
    }

    /**
     * Page size 4 splits the `paid` group across pages, so Filament omits the trailing per-group row
     * on page 1 and the totals follow a record row (or the page-summary header row) directly.
     */
    private function splitGroupTable(Table $table, SummaryGroup $group): Table
    {
        return $table
            ->defaultGroup($group->hideTrailingSummary())
            ->paginated([4])
            ->defaultPaginationPageOption(4);
    }

    public function test_group_split_across_pages_keeps_page_and_all_records_totals(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $this->splitGroupTable($table, $group);

        $html = Livewire::test(OrdersTable::class)->html();
        $this->assertStringContainsString('fi-ta-summary-header-row', $html);

        ['rows' => $rows, 'hidden' => $hidden] = $this->summaryRows($html);

        // Page 1: open (3) + paid (1 of 2) -> one per-group row (open), then the page total and the all-records total.
        $this->assertCount(3, $rows);
        $this->assertSame([$rows[0]], $hidden);
    }

    public function test_group_split_across_pages_without_page_summary_keeps_all_records_total(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $this->splitGroupTable($table, $group)
            ->summaries(pageCondition: false);

        $html = Livewire::test(OrdersTable::class)->html();
        $this->assertStringNotContainsString('fi-ta-summary-header-row', $html);

        ['rows' => $rows, 'hidden' => $hidden] = $this->summaryRows($html);

        // Page 1: one per-group row (open), then the all-records total right after the last `paid` record.
        $this->assertCount(2, $rows);
        $this->assertSame([$rows[0]], $hidden);
    }

    public function test_group_split_across_cursor_pages_keeps_all_records_total(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $this->splitGroupTable($table, $group)
            ->paginationMode(PaginationMode::Cursor);

        $html = Livewire::test(OrdersTable::class)->html();
        $this->assertStringNotContainsString('fi-ta-summary-header-row', $html);

        ['rows' => $rows, 'hidden' => $hidden] = $this->summaryRows($html);

        // Cursor pagination never renders a page summary: one per-group row (open), then the all-records total.
        $this->assertCount(2, $rows);
        $this->assertSame([$rows[0]], $hidden);
    }

    public function test_without_any_totals_only_the_last_per_group_row_stays_visible(): void
    {
        $this->seedOrders();
        OrdersTable::$configure = fn (Table $table, SummaryGroup $group): Table => $table
            ->defaultGroup($group->hideTrailingSummary())
            ->summaries(pageCondition: false, allTableCondition: false);

        ['rows' => $rows, 'hidden' => $hidden] = $this->summaryRows(Livewire::test(OrdersTable::class)->html());

        // Documented limit: the last per-group row is the last row of the <tbody>, indistinguishable from a total.
        $this->assertCount(3, $rows);
        $this->assertSame(array_slice($rows, 0, 2), $hidden);
    }
}
