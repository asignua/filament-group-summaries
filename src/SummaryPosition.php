<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries;

/**
 * Where a SummaryGroup draws its summary row.
 */
enum SummaryPosition: string
{
    /**
     * Filament's own placement: after the group's records. The default.
     */
    case Bottom = 'bottom';

    /**
     * Right under the group header, aligned under the table columns; the row after the records is hidden.
     */
    case Top = 'top';

    /**
     * Under the group header AND after the records.
     */
    case Both = 'both';

    public function hasTop(): bool
    {
        return $this !== self::Bottom;
    }

    public function hasBottom(): bool
    {
        return $this !== self::Top;
    }
}
