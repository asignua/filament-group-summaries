## Filament Group Summaries (asignua/filament-group-summaries)

- Use `Asignua\FilamentGroupSummaries\SummaryGroup::make('status')` instead of `Filament\Tables\Grouping\Group` in `$table->groups([...])` / `defaultGroup()`. It is a normal `Group` subclass (all its methods work).
- `->summariesInHeader()` prints the group's column summaries (the same summarizers as the table's columns: Sum, Average, Count, Range, Values, custom) in the group header row; it stays visible when the group is collapsed. `->summaryColumns(['amount'])` limits the columns, `->columnLabels(false)` drops the column names.
- `->hideTrailingSummary()` hides the per-group summary rows Filament draws after each group (CSS only; the page / all-records totals stay, on single- and multi-page tables). It is per Group definition, not per group value.
- `->defaultDirection('desc')` is applied when the user picks that group in the dropdown (a Livewire hook) and, for the table's default group, via `$group->makeDefaultOn($table)` (plain `$table->defaultGroup($group)` keeps `Table::defaultGroup()`'s own 'asc').
- Register `GroupSummariesPlugin::make()` on the panel (it links the stylesheet). Without the plugin the data is right but unstyled.
- Header summaries run ONE extra grouped aggregate per render (cached for all headers). Summarizers that query on their own cost their queries once per group header (on top of Filament's trailing rows): `->query(...)` modifications, `Values`, `Count::icons()` and `->using(...)` callbacks.
- The header lives in a `<p>`: block tags in a summarizer's `toEmbeddedHtml()` (div, ul, li, p, table, ...) are turned into `<span>`, so style a custom summarizer with classes, not with its tag names.
