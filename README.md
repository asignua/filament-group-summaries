# Filament Group Summaries

[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://stand-with-ukraine.pp.ua)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/asignua/filament-group-summaries.svg?style=flat-square)](https://packagist.org/packages/asignua/filament-group-summaries)
[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-group-summaries/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/asignua/filament-group-summaries/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/asignua/filament-group-summaries.svg?style=flat-square)](https://packagist.org/packages/asignua/filament-group-summaries)
[![License](https://img.shields.io/packagist/l/asignua/filament-group-summaries.svg?style=flat-square)](https://github.com/asignua/filament-group-summaries/blob/main/LICENSE.md)
[![Plumb score](https://plumbphp.dev/badges/asignua/filament-group-summaries/composite.svg)](https://plumbphp.dev/asignua/filament-group-summaries)

<img class="filament-hidden" src="https://raw.githubusercontent.com/asignua/filament-group-summaries/v1.0.0/art/cover.jpg" alt="Filament Group Summaries">

Show a [Filament](https://filamentphp.com) table's column summaries **in the group header row**
(they stay visible when the group is collapsed), hide the summary row drawn after each group, and
give a group its own default sort direction. No Filament view is forked.

Requested in [filamentphp/filament#19615](https://github.com/filamentphp/filament/discussions/19615)
(summaries in the group header), with related asks
[#15353](https://github.com/filamentphp/filament/discussions/15353) (hide the summary per group) and
[#19753](https://github.com/filamentphp/filament/discussions/19753) (default direction for groups).

- [Screenshots](#screenshots)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [How it works and its trade-offs](#how-it-works-and-its-trade-offs)
- [Gotchas](#gotchas)
- [AI agents](#ai-agents)
- [Testing](#testing)

## Screenshots

![Summaries in the group header, one group collapsed](https://raw.githubusercontent.com/asignua/filament-group-summaries/v1.0.0/art/group-header.jpg)

![The same in dark mode](https://raw.githubusercontent.com/asignua/filament-group-summaries/v1.0.0/art/group-header-dark.jpg)

## Requirements

- PHP 8.3+
- Filament 5.9+ (`composer.json` requires `^5.9`). Developed and tested against **Filament 5.9** (`filament/tables` v5.9.0). The plugin relies on a few
  Filament internals (listed in [How it works](#how-it-works-and-its-trade-offs)); `tests/FilamentInternalsTest.php`
  fails with a message naming what moved if a Filament update breaks one.

## Installation

```bash
composer require asignua/filament-group-summaries
php artisan filament:assets
```

Register the plugin on the panel (it links a tiny stylesheet after your theme):

```php
use Asignua\FilamentGroupSummaries\GroupSummariesPlugin;

$panel->plugin(GroupSummariesPlugin::make());
```

## Usage

Use `SummaryGroup` wherever you used `Group`; it extends it, so everything you already call still works.

```php
use Asignua\FilamentGroupSummaries\SummaryGroup;
use Filament\Tables\Columns\Summarizers\Sum;

$table
    ->columns([
        TextColumn::make('status'),
        TextColumn::make('amount')->summarize(Sum::make()->label('Total')),
    ])
    ->groups([
        SummaryGroup::make('status')
            ->collapsible()
            ->summariesInHeader()     // Sum / Average / Count / Range / Values / custom, per group
            ->hideTrailingSummary()   // drop the row Filament draws after each group
            ->defaultDirection('desc'),
    ]);
```

| Method | What it does |
| --- | --- |
| `summariesInHeader(bool\|Closure = true)` | Prints each group's summaries in its header row. Values respect filters and search. |
| `summaryColumns(?array $names)` | Limits the header to these columns (default: every visible column with a summarizer). |
| `columnLabels(bool = true)` | Show or hide the column name before its summaries. |
| `hideTrailingSummary(bool\|Closure = true)` | Hides the per-group summary rows after the groups (the page / all-records totals in the footer stay). |
| `defaultDirection('asc'\|'desc')` | Direction applied when the user picks this group in the grouping dropdown. |
| `makeDefaultOn(Table $table)` | `defaultGroup()` that uses the group's own direction. |

For the table's default group use `makeDefaultOn()`; plain `->defaultGroup($group)` keeps
`Table::defaultGroup()`'s own direction argument (`'asc'`).

## How it works and its trade-offs

Filament has no hook for a group header, and `index.blade.php` is huge and changes every release, so the view
is **not overridden**. Instead:

- **Header summaries.** The header prints the group's *description* with `{{ }}`, which renders any `Htmlable`
  unescaped. `SummaryGroup::getDescription()` returns your description (if any) plus a strip of summaries. The values
  come from Filament's own `getTableSummarySelectedState()` (the same grouped query Filament runs for its summary rows),
  rendered by the columns' own summarizers, so formatting, labels and custom summarizers match the footer.
  The strip is cached per query, so any number of group headers cost **one** extra aggregate query per render.
- **Hide the trailing row.** A marker element is emitted into the header and a CSS `:has()` rule hides a
  `tr.fi-ta-summary-row` that directly follows a record row and is not the last row of the `tbody`: that is always a
  per-group row (the next group header or the totals follow it). The page / all-records totals sit in the same `tbody`
  but follow a summary row or the page-summary header row; when the last group continues on the next page Filament
  skips its trailing row, and without a page-summary header (page summaries off, cursor pagination) the all-records
  total follows a record row, but it is then the last row and stays visible. So the totals stay visible on single-page,
  multi-page, cursor-paginated and unpaginated tables alike. One limit: with
  `summaries(allTableCondition: false)` on a page without a page-summary header row, the very last per-group row is the
  last row of the `tbody` and stays visible. If a Filament update changes that markup,
  the rows simply stay visible.
- **Default direction.** The dropdown always sends `group:asc` the moment a group is picked, so a Livewire component
  hook (registered by the service provider) swaps in the group's direction when the *group* changes. A later explicit
  direction change inside the same group is respected.

Trade-offs, honestly:

- The header is a single cell spanning the whole row, so the values are a **strip of "Column Label value" items under the
  group title**, not cells aligned under their columns. Aligned cells would need a fork of Filament's table view.
- Filament still computes its own grouped aggregate for the (hidden) trailing rows: header summaries add **one** query
  on top of Filament's one.
- The strip sits in the group description, which is a `<p>`: summarizer HTML is flattened to inline elements (block
  tags such as `<div>`, `<ul>`, `<li>` become `<span>`, `Values` without a custom `->view()` becomes a comma-separated list; summarizers are rendered with `toHtml()`, so a custom `->view()` shows in the header as in the footer).

## Gotchas

- Only aggregate summarizers (Sum, Average, plain Count, Range) use the shared grouped query. Summarizers that query on
  their own run their queries **once per group header**, on top of the ones Filament runs for its trailing rows:
  `->query(...)` modifications, `Values` (a `distinct()->pluck()`), `Count::icons()` (a pluck plus a count per value)
  and `->using(...)` callbacks.
- `hideTrailingSummary()` is per `SummaryGroup`, not per group value (CSS cannot tell which trailing row belongs to
  which group).
- Tables without an Eloquent summary query (custom array data) get no header summaries.
- `hideTrailingSummary()` is CSS only. Without the stylesheet the header strip is unstyled **and** `hideTrailingSummary()`
  has no effect. `GroupSummariesPlugin` links it in a panel; outside a panel (a standalone Livewire table, or a panel
  without the plugin) add it to the layout yourself:
  `<link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('filament-group-summaries', 'asignua/filament-group-summaries') }}">`.

## AI agents

`resources/boost/guidelines/core.blade.php` ships Laravel Boost guidelines.

## Testing

```bash
composer install
vendor/bin/phpunit
```

See the [CHANGELOG](https://github.com/asignua/filament-group-summaries/blob/main/CHANGELOG.md) and the
[LICENSE](https://github.com/asignua/filament-group-summaries/blob/main/LICENSE.md).
