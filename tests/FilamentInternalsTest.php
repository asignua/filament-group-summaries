<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries\Tests;

use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Concerns\CanSummarizeRecords;
use Filament\Tables\Grouping\Group;
use Illuminate\Support\HtmlString;
use Livewire\ComponentHook;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;

/**
 * The plugin leans on a few Filament internals instead of forking its views. If a Filament
 * update moves any of them, THIS file fails first, with a message that names what changed.
 */
class FilamentInternalsTest extends TestCase
{
    private function tablesPackage(): string
    {
        return dirname((string) (new ReflectionClass(Group::class))->getFileName(), 3);
    }

    /**
     * What Filament's table renders, wherever it lives: up to 5.11 the Blade `index.blade.php`, from 5.12 the PHP
     * `Table\Concerns\*` traits (the markup is the same, only the file moved).
     */
    private function tablesView(): string
    {
        $package = $this->tablesPackage();
        $files = array_filter([
            $package.'/resources/views/index.blade.php',
            $package.'/src/Table/Concerns/CanGroupRecords.php',
            $package.'/src/Table/Concerns/CanSummarizeRecords.php',
            $package.'/src/Table/Concerns/HasRecords.php',
        ], 'is_file');

        $this->assertNotEmpty($files, 'Filament moved the table rendering again: update tablesView().');

        return implode("\n", array_map(fn (string $file): string => (string) file_get_contents($file), $files));
    }

    public function test_the_group_header_still_prints_the_description_unescaped_for_htmlable(): void
    {
        $view = $this->tablesView();

        $this->assertStringContainsString('class="fi-ta-group-description"', $view);
        $this->assertMatchesRegularExpression('/->getDescription\(\$record, \$recordGroupTitle\)/', $view);
        // Blade `{{ }}` (<= 5.11) or PHP `e()` (>= 5.12): both print an Htmlable's HTML as it is.
        $this->assertMatchesRegularExpression('/\{\{ \$recordGroupDescription \}\}|e\(\$recordGroupDescription\)/', $view);
        $this->assertSame('<b>x</b>', e(new HtmlString('<b>x</b>')));
    }

    public function test_the_view_still_computes_the_grouped_summary_state_with_group_query(): void
    {
        $this->assertMatchesRegularExpression(
            '/getTableSummarySelectedState\(\s*\$(?:this|livewire)->getAllTableSummaryQuery\(\),\s*modifyQueryUsing: fn \((?:Builder|QueryBuilder) \$query\) => \$group->groupQuery\(/',
            $this->tablesView(),
        );
    }

    /**
     * The hide-trailing CSS tells per-group summary rows from the totals by what precedes them:
     * a per-group row directly follows a record row; the totals follow a summary row or the
     * page-summary header row, which only exists on multi-page tables.
     */
    public function test_summary_rows_markup_the_hide_trailing_css_relies_on(): void
    {
        $views = $this->tablesPackage().'/resources/views';
        $source = $this->tablesView();
        $summary = (string) file_get_contents($views.'/components/summary/index.blade.php');
        $row = (string) file_get_contents($views.'/components/summary/row.blade.php');

        $this->assertStringContainsString("\$attributes->class(['fi-ta-row fi-ta-summary-row'])", $row);
        // The page-summary header row: Blade component (<= 5.11) or the same markup built in PHP (>= 5.12).
        $this->assertStringContainsString('<tr class="fi-ta-row fi-ta-summary-header-row', $summary.$source);
        $this->assertMatchesRegularExpression('/\$hasPageSummary = \$pageSummary && \(! \$groupsOnly\) && \(?\$records instanceof Paginator\)? && \$records->hasPages\(\);/', $summary.$source);
        $this->assertMatchesRegularExpression('/if \(\$hasPageSummary\)[^<]*\{?\s*(?:\$html \.= \x27|)<tr class="fi-ta-row fi-ta-summary-header-row/', $summary.$source);
        // Group rows and the totals are drawn in the same <tbody> as the records.
        $this->assertMatchesRegularExpression('/<x-filament-tables::summary\s+:actions="\$hasRecordActionsForAnyRecord"|<tbody>.*?generateSummaryHtml\(/s', $source);
    }

    #[DataProvider('signatures')]
    public function test_the_methods_the_plugin_calls_keep_their_signature(string $class, string $method, string $expectedParameters): void
    {
        $this->assertTrue(method_exists($class, $method), "{$class}::{$method}() is gone.");

        $parameters = array_map(
            fn ($parameter): string => $parameter->getName(),
            (new ReflectionMethod($class, $method))->getParameters(),
        );

        $this->assertSame($expectedParameters, implode(',', $parameters), "{$class}::{$method}() changed its parameters.");
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function signatures(): array
    {
        return [
            'selected state' => [CanSummarizeRecords::class, 'getTableSummarySelectedState', 'query,modifyQueryUsing'],
            'description' => [Group::class, 'getDescription', 'record,title'],
            'group query' => [Group::class, 'groupQuery', 'query,model'],
            'scope query' => [Group::class, 'scopeQuery', 'query,record'],
            'string key' => [Group::class, 'getStringKey', 'record'],
            'embedded html' => [Summarizer::class, 'toEmbeddedHtml', ''],
            'hook' => [ComponentHook::class, 'callUpdate', 'propertyName,fullPath,newValue'],
        ];
    }
}
