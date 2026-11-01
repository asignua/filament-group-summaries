# Changelog

All notable changes to `asignua/filament-group-summaries` are documented here.

## v1.1.0 - 2026-11-01

- `SummaryGroup::position(SummaryPosition|Closure)`: `Bottom` (default, unchanged), `Top` (summary row under the group header, aligned under the table columns; the row after the records is hidden) or `Both`. Requested in filamentphp/filament#19615 (and #20227 for the table-level summary).
- `SummaryGroup::color(string|array|Closure|null)`: tints the group header with a Filament colour, by name, by group key / title map, or per group with a closure; light and dark mode (cf. filamentphp/filament#20228).
- New Alpine component `group-summaries-top` (loaded on demand) for the aligned row.
- Workbench: demo page `/admin/orders` and a seeder; art for the new options.

## v1.0.2 - 2026-10-25

- Screenshots and the cover carry the «made in asign» mark; README shows the Stand With Ukraine badge.
- Dependency updates (CI actions, dev tooling).

## v1.0.1 - 2026-10-08

- Header summarizers render through `toHtml()` (like the footer), so a custom `->view()` and published view overrides show in the group header; `Values` with a custom view is no longer flattened.
- `<pre>`, `<blockquote>`, `<figure>`, `<nav>` and the other tags that implicitly close a `<p>` are flattened to `<span>` in the header strip; `<hr>` is dropped.
- Docs: `hideTrailingSummary()` needs the stylesheet, which outside a panel must be linked by hand.

## v1.0.0 - 2026-10-05

- `SummaryGroup` (a `Group` subclass): `summariesInHeader()`, `summaryColumns()`, `columnLabels()`, `hideTrailingSummary()`, `defaultDirection()`, `makeDefaultOn()`.
- Livewire hook that applies a group's default direction when the group is picked in the grouping dropdown.
- `GroupSummariesPlugin` links the stylesheet after the panel theme.
- Laravel Boost guidelines for coding agents.
