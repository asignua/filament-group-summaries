<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries;

use Closure;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\Summarizers\Values;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * A Filament table `Group` that can show the group's column summaries IN the group header
 * (collapsed groups keep them visible), hide the summary row drawn after the group, and carry
 * its own default sort direction.
 *
 * It does not fork any Filament view: the header's description slot accepts `Htmlable`, and the
 * per-group aggregates come from Filament's own `getTableSummarySelectedState()`.
 */
class SummaryGroup extends Group
{
    protected bool|Closure $summariesInHeader = false;

    protected bool|Closure $hidesTrailingSummary = false;

    protected string|Closure|null $defaultDirection = null;

    /** @var array<int, string>|null */
    protected ?array $summaryColumns = null;

    protected bool $showsColumnLabels = true;

    /** @var array<string, array<string, array<string, mixed>>> */
    protected array $stateCache = [];

    public function summariesInHeader(bool|Closure $condition = true): static
    {
        $this->summariesInHeader = $condition;

        return $this;
    }

    public function hideTrailingSummary(bool|Closure $condition = true): static
    {
        $this->hidesTrailingSummary = $condition;

        return $this;
    }

    public function defaultDirection(string|Closure|null $direction): static
    {
        $this->defaultDirection = $direction;

        return $this;
    }

    /**
     * Restricts the header to these columns (by name). Default: every visible column with a summarizer.
     *
     * @param array<int, string>|null $columns
     */
    public function summaryColumns(?array $columns): static
    {
        $this->summaryColumns = $columns;

        return $this;
    }

    public function columnLabels(bool $condition = true): static
    {
        $this->showsColumnLabels = $condition;

        return $this;
    }

    public function hasSummariesInHeader(): bool
    {
        return (bool) $this->evaluate($this->summariesInHeader);
    }

    public function hidesTrailingSummary(): bool
    {
        return (bool) $this->evaluate($this->hidesTrailingSummary);
    }

    /**
     * 'asc' or 'desc' when set, null when the group has no opinion.
     */
    public function getDefaultDirection(): ?string
    {
        $direction = $this->evaluate($this->defaultDirection);

        if (!is_string($direction)) {
            return null;
        }

        $direction = strtolower($direction);

        return in_array($direction, ['asc', 'desc'], true) ? $direction : null;
    }

    /**
     * Makes this the table's default group and uses its own default direction
     * (`Table::defaultGroup()` has a direction of its own, which would win otherwise).
     */
    public function makeDefaultOn(Table $table): Table
    {
        return $table->defaultGroup($this, fn (): string => $this->getDefaultDirection() ?? 'asc');
    }

    /**
     * @param array<string, mixed>|Model $record
     */
    public function getDescription(Model|array $record, string|Htmlable|null $title): string|Htmlable|null
    {
        $description = parent::getDescription($record, $title);

        $marker = $this->hidesTrailingSummary() ? '<span hidden data-fi-gs="hide-trailing"></span>' : '';
        $strip = ($this->hasSummariesInHeader() && $record instanceof Model) ? $this->renderStrip($record) : '';

        if ($marker === '' && $strip === '') {
            return $description;
        }

        $base = '';

        if (filled($description)) {
            $base = '<span class="fi-gs-description">'
                .($description instanceof Htmlable ? $description->toHtml() : e($description))
                .'</span>';
        }

        return new HtmlString($base.$strip.$marker);
    }

    private function renderStrip(Model $record): string
    {
        $livewire = $this->getLivewire();
        $query = $livewire->getAllTableSummaryQuery();

        if (!$query instanceof Builder) {
            return '';
        }

        $state = $this->groupState($livewire, $query)[(string) $this->getStringKey($record)] ?? [];
        $scoped = $this->scopeQuery($query->clone(), $record);

        $items = [];

        foreach ($this->getTable()->getVisibleColumns() as $column) {
            if ($this->summaryColumns !== null && !in_array($column->getName(), $this->summaryColumns, true)) {
                continue;
            }

            $summaries = [];

            foreach ($column->getSummarizers($scoped) as $summarizer) {
                $summaries[] = $this->renderSummarizer($summarizer->query($scoped)->selectedState($state));
            }

            if ($summaries === []) {
                continue;
            }

            $label = $this->showsColumnLabels
                ? '<span class="fi-gs-column">'.e($column->getLabel()).'</span>'
                : '';

            $items[] = '<span class="fi-gs-item">'.$label.implode('', $summaries).'</span>';
        }

        if ($items === []) {
            return '';
        }

        return '<span class="fi-gs-strip">'.implode('', $items).'</span>';
    }

    /**
     * The group description sits in a `<p>`: a block tag (div, ul) would close it and break the
     * markup, so the summarizer's root `<div>` is turned into a `<span>`, and the bulleted list of
     * `Values` is flattened to a comma-separated line.
     */
    private function renderSummarizer(Summarizer $summarizer): string
    {
        if ($summarizer instanceof Values) {
            $values = array_map(
                fn (mixed $item): string => e((string) $summarizer->formatState($item)),
                (array) $summarizer->getState(),
            );

            $label = filled($label = $summarizer->getLabel()) ? '<span class="fi-ta-values-summary-label">'.e($label).'</span> ' : '';

            return '<span class="fi-ta-values-summary">'.$label.'<span>'.implode(', ', $values).'</span></span>';
        }

        $html = trim((string) preg_replace('/\s+/', ' ', $summarizer->toEmbeddedHtml()));

        return (string) preg_replace(['/\A<div\b/', '/<\/div>\z/'], ['<span', '</span>'], $html);
    }

    /**
     * The same one grouped aggregate query Filament runs for its own summary rows, cached per
     * (filtered query, group): N group headers cost one query, not N.
     *
     * @param Builder<Model> $query
     *
     * @return array<string, array<string, mixed>>
     */
    private function groupState(HasTable $livewire, Builder $query): array
    {
        if (!method_exists($livewire, 'getTableSummarySelectedState')) {
            return [];
        }

        $key = md5($query->toSql().json_encode($query->getBindings()));

        return $this->stateCache[$key] ??= $livewire->getTableSummarySelectedState(
            $query->clone(),
            modifyQueryUsing: fn ($aggregate) => $this->groupQuery($aggregate, model: $query->getModel()),
        );
    }
}
