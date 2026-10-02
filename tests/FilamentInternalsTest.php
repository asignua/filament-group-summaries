<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries\Tests;

use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Concerns\CanSummarizeRecords;
use Filament\Tables\Grouping\Group;
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
    private function tablesView(): string
    {
        $path = (new ReflectionClass(Group::class))->getFileName();

        return (string) file_get_contents(dirname((string) $path, 3).'/resources/views/index.blade.php');
    }

    public function test_the_group_header_still_prints_the_description_unescaped_for_htmlable(): void
    {
        $view = $this->tablesView();

        $this->assertStringContainsString('class="fi-ta-group-description"', $view);
        $this->assertStringContainsString('$group->getDescription($record, $recordGroupTitle)', $view);
        $this->assertStringContainsString('{{ $recordGroupDescription }}', $view);
    }

    public function test_the_view_still_computes_the_grouped_summary_state_with_group_query(): void
    {
        $this->assertStringContainsString(
            'getTableSummarySelectedState($this->getAllTableSummaryQuery(), modifyQueryUsing: fn (Builder $query) => $group->groupQuery(',
            $this->tablesView(),
        );
    }

    public function test_group_summary_rows_still_sit_in_tbody_and_totals_in_tfoot(): void
    {
        $view = $this->tablesView();

        $this->assertStringContainsString('<x-filament-tables::summary.row', $view);
        $this->assertMatchesRegularExpression('/<\/tbody>\s*@endif\s*@if \(\(\$records !== null\) && count\(\$records\) && \$contentFooter\)\s*<tfoot>/', $view);
        $this->assertStringContainsString('fi-ta-summary-row', (string) file_get_contents(dirname((new ReflectionClass(Group::class))->getFileName() ?: '', 3).'/resources/views/components/summary/row.blade.php'));
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
