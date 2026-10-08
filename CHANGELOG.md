# Changelog

All notable changes to `asignua/filament-group-summaries` are documented here.

## v1.0.1 - 2026-10-08

- Header summarizers render through `toHtml()` (like the footer), so a custom `->view()` and published view overrides show in the group header; `Values` with a custom view is no longer flattened.
- `<pre>`, `<blockquote>`, `<figure>`, `<nav>` and the other tags that implicitly close a `<p>` are flattened to `<span>` in the header strip; `<hr>` is dropped.
- Docs: `hideTrailingSummary()` needs the stylesheet, which outside a panel must be linked by hand.

## v1.0.0 - 2026-10-05

- `SummaryGroup` (a `Group` subclass): `summariesInHeader()`, `summaryColumns()`, `columnLabels()`, `hideTrailingSummary()`, `defaultDirection()`, `makeDefaultOn()`.
- Livewire hook that applies a group's default direction when the group is picked in the grouping dropdown.
- `GroupSummariesPlugin` links the stylesheet after the panel theme.
- Laravel Boost guidelines for coding agents.
