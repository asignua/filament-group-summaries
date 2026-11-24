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
     * What Filament's table renders: since 5.12 the PHP `Table\Concerns\*` traits (the Blade `index.blade.php` is gone).
     */
    private function tablesView(): string
    {
        $concerns = $this->tablesPackage().'/src/Table/Concerns';

        return implode("\n", array_map(
            fn (string $file): string => (string) file_get_contents($concerns.'/'.$file.'.php'),
            ['CanGroupRecords', 'CanSummarizeRecords', 'HasRecords'],
        ));
    }

    public function test_the_group_header_still_prints_the_description_unescaped_for_htmlable(): void
    {
        $view = $this->tablesView();

        $this->assertStringContainsString('class="fi-ta-group-description"', $view);
        $this->assertStringContainsString('->getDescription($record, $recordGroupTitle)', $view);
        // `e()` prints an Htmlable's HTML as it is.
        $this->assertStringContainsString('<?= e($recordGroupDescription); ?>', $view);
        $this->assertSame('<b>x</b>', e(new HtmlString('<b>x</b>')));
    }

    public function test_the_view_still_computes_the_grouped_summary_state_with_group_query(): void
    {
        $this->assertMatchesRegularExpression(
            '/getTableSummarySelectedState\(\s*\$livewire->getAllTableSummaryQuery\(\),\s*modifyQueryUsing: fn \(QueryBuilder \$query\) => \$group->groupQuery\(/',
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
        $row = (string) file_get_contents($this->tablesPackage().'/resources/views/components/summary/row.blade.php');
        $source = $this->tablesView();

        $this->assertStringContainsString("\$attributes->class(['fi-ta-row fi-ta-summary-row'])", $row);
        $this->assertStringContainsString("\$html .= '<tr class=\"fi-ta-row fi-ta-summary-header-row", $source);
        $this->assertStringContainsString('$hasPageSummary = $pageSummary && (! $groupsOnly) && ($records instanceof Paginator) && $records->hasPages();', $source);
        $this->assertMatchesRegularExpression('/if \(\$hasPageSummary\) \{\s*\$html \.= \'<tr class="fi-ta-row fi-ta-summary-header-row/', $source);
        // Group rows and the totals are drawn in the same <tbody> as the records.
        $this->assertMatchesRegularExpression('/<tbody>.*?generateSummaryHtml\(/s', $source);
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
