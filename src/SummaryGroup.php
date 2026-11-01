<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries;

use Closure;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentAsset;

use function Filament\Support\get_color_css_variables;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\Summarizers\Values;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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
    /** Tags whose start tag closes an open `<p>` and that are turned into `<span>` in summarizer HTML (`<hr>` is void and stripped separately). */
    private const BLOCK_TAGS = 'div|ul|ol|li|p|dl|dt|dd|table|thead|tbody|tfoot|tr|td|th|section|header|footer|h[1-6]'
        .'|address|article|aside|blockquote|details|dialog|fieldset|figcaption|figure|form|hgroup|main|menu|nav|pre|search|summary';

    protected bool|Closure $summariesInHeader = false;

    protected bool|Closure $hidesTrailingSummary = false;

    protected string|Closure|null $defaultDirection = null;

    protected SummaryPosition|Closure $position = SummaryPosition::Bottom;

    /** @var array<string, string>|Closure|string|null */
    protected string|array|Closure|null $color = null;

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

    /**
     * Where the group's summary row is drawn: after the records (Filament's own, the default),
     * right under the header aligned under the columns, or both.
     */
    public function position(SummaryPosition|Closure $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Tints the group header with a Filament colour: a colour name (`'success'`, or any colour registered
     * with `FilamentColor`), an array map from group key or title to a colour name, or a closure per
     * group (`$record`, `$title`, `$key`, `$state`) returning a name, an array of shades, or null for
     * the neutral header.
     *
     * @param array<string, string>|Closure|string|null $color
     */
    public function color(string|array|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getPosition(): SummaryPosition
    {
        $position = $this->evaluate($this->position);

        return $position instanceof SummaryPosition ? $position : SummaryPosition::Bottom;
    }

    /**
     * @param array<string, mixed>|Model $record
     *
     * @return array<int|string, string>|string|null
     */
    public function getColor(Model|array $record, string|Htmlable|null $title): string|array|null
    {
        if (is_array($this->color) && !self::isShadeMap($this->color)) {
            $key = $this->getStringKey($record);
            $titleText = $title instanceof Htmlable ? $title->toHtml() : $title;

            return self::validColor($this->color[$key ?? ''] ?? $this->color[(string) $titleText] ?? null);
        }

        $color = $this->evaluate(
            $this->color,
            namedInjections: ['record' => $record, 'title' => $title, 'key' => $this->getStringKey($record), 'state' => $this->getKey($record)],
            typedInjections: ($record instanceof Model) ? [Model::class => $record, $record::class => $record] : [],
        );

        return self::validColor($color);
    }

    /**
     * A colour is a non-empty name or a shade map (`Color::Purple`); a map without the 400 and 500 shades
     * the tint uses is ignored rather than failing the whole table.
     *
     * @return array<int|string, string>|string|null
     */
    private static function validColor(mixed $color): string|array|null
    {
        if (is_string($color) && $color !== '') {
            return $color;
        }

        return is_array($color) && self::isShadeMap($color) ? $color : null;
    }

    /**
     * @param array<int|string, mixed> $color
     */
    private static function isShadeMap(array $color): bool
    {
        return isset($color[400], $color[500]);
    }

    public function hasSummariesInHeader(): bool
    {
        return (bool) $this->evaluate($this->summariesInHeader);
    }

    public function hidesTrailingSummary(): bool
    {
        return (bool) $this->evaluate($this->hidesTrailingSummary) || !$this->getPosition()->hasBottom();
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
        $color = $this->renderColor($record, $title);
        $summaries = '';

        if ($record instanceof Model) {
            if ($this->getPosition()->hasTop()) {
                $summaries = $this->renderTopRow($record);
            } elseif ($this->hasSummariesInHeader()) {
                $summaries = $this->renderStrip($record);
            }
        }

        if ($marker === '' && $summaries === '' && $color === '') {
            return $description;
        }

        $base = '';

        if (filled($description)) {
            $base = '<span class="fi-gs-description">'
                .($description instanceof Htmlable ? $description->toHtml() : e($description))
                .'</span>';
        }

        return new HtmlString($base.$summaries.$marker.$color);
    }

    /**
     * An empty element that paints the header's tint (see group-summaries.css): it can carry the colour
     * variables, while the header cell it tints belongs to Filament's view.
     *
     * @param array<string, mixed>|Model $record
     */
    private function renderColor(Model|array $record, string|Htmlable|null $title): string
    {
        $color = $this->getColor($record, $title);

        if ($color === null) {
            return '';
        }

        /** @var array{50: string, 100: string, 200: string, 300: string, 400: string, 500: string, 600: string, 700: string, 800: string, 900: string, 950: string}|string $color */
        $variables = get_color_css_variables($color, shades: [400, 500]);

        return $variables === null ? '' : '<span class="fi-gs-color" aria-hidden="true" style="'.e($variables).'"></span>';
    }

    /**
     * The summaries of every visible column of one group: `[column name => [column, [html, ...]]]`.
     *
     * @return array<string, array{0: Column, 1: array<int, string>}>
     */
    private function columnSummaries(Model $record, bool $allColumns): array
    {
        $livewire = $this->getLivewire();
        $query = $livewire->getAllTableSummaryQuery();

        if (!$query instanceof Builder) {
            return [];
        }

        $state = $this->groupState($livewire, $query)[(string) $this->getStringKey($record)] ?? [];
        $scoped = $this->scopeQuery($query->clone(), $record);

        $result = [];

        foreach ($this->getTable()->getVisibleColumns() as $column) {
            if ($this->summaryColumns !== null && !in_array($column->getName(), $this->summaryColumns, true)) {
                continue;
            }

            $summaries = [];

            foreach ($column->getSummarizers($scoped) as $summarizer) {
                $summaries[] = $this->renderSummarizer($summarizer->query($scoped)->selectedState($state));
            }

            if ($summaries !== [] || $allColumns) {
                $result[$column->getName()] = [$column, $summaries];
            }
        }

        return $result;
    }

    /**
     * The group's summary row under its header: one cell per visible column, lined up under the table's
     * header cells by `group-summaries-top.js` (a wrapping strip where that is not possible).
     */
    private function renderTopRow(Model $record): string
    {
        $columns = $this->columnSummaries($record, allColumns: true);

        if (collect($columns)->every(fn (array $item): bool => $item[1] === [])) {
            return '';
        }

        $cells = '';

        foreach ($columns as [$column, $summaries]) {
            $alignment = $column->getAlignment();
            $alignment = $alignment instanceof Alignment ? $alignment->value : (string) $alignment;
            $align = in_array($alignment, ['end', 'right'], true) ? 'end' : ($alignment === 'center' ? 'center' : 'start');

            $label = $this->showsColumnLabels && $summaries !== []
                ? '<span class="fi-gs-column">'.e($column->getLabel()).'</span>'
                : '';

            $cells .= '<span class="fi-gs-top-cell fi-gs-align-'.$align.'" data-gs-th="fi-ta-header-cell-'.e(str($column->getName())->camel()->kebab()->toString()).'">'
                .$label.implode('', $summaries).'</span>';
        }

        $src = FilamentAsset::getAlpineComponentSrc('group-summaries-top', GroupSummariesServiceProvider::PACKAGE);

        return '<span class="fi-gs-top" x-load x-load-src="'.e($src).'" x-data="groupSummariesTop">'.$cells.'</span>';
    }

    private function renderStrip(Model $record): string
    {
        $items = [];

        foreach ($this->columnSummaries($record, allColumns: false) as [$column, $summaries]) {
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
     * The group description sits in a `<p>`: a block tag (div, ul, li, ...) would close it and break
     * the markup. So the bulleted list of `Values` is flattened to a comma-separated line, and in any
     * other summarizer's HTML (`Count::icons()`, custom ones) every block tag becomes a `<span>`.
     */
    private function renderSummarizer(Summarizer $summarizer): string
    {
        if ($summarizer instanceof Values && !$summarizer->hasView()) {
            $state = $summarizer->getState();

            $values = array_map(
                function (mixed $item) use ($summarizer): string {
                    $formatted = $summarizer->formatState($item);

                    return $formatted instanceof Htmlable ? $formatted->toHtml() : e($formatted);
                },
                $state instanceof Arrayable ? $state->toArray() : Arr::wrap($state),
            );

            $attributes = $summarizer->getExtraAttributeBag()->class(['fi-ta-values-summary'])->toHtml();
            $label = filled($label = $summarizer->getLabel()) ? '<span class="fi-ta-values-summary-label">'.e($label).'</span> ' : '';

            return '<span '.$attributes.'>'.$label.'<span>'.implode(', ', $values).'</span></span>';
        }

        // toHtml(), not toEmbeddedHtml(): the footer renders `{{ $summarizer }}`, so a custom ->view()
        // and a published embedded-view override must show in the header too.
        $html = trim((string) preg_replace('/\s+/', ' ', $summarizer->toHtml()));
        $html = (string) preg_replace('/<hr\b[^>]*>/i', ' ', $html);

        return (string) preg_replace('/<(\/?)(?:'.self::BLOCK_TAGS.')\b/i', '<$1span', $html);
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
