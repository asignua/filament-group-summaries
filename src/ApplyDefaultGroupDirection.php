<?php

declare(strict_types=1);

namespace Asignua\FilamentGroupSummaries;

use Closure;
use Filament\Tables\Contracts\HasTable;
use Livewire\ComponentHook;

/**
 * Filament's grouping dropdown sends `group:asc` the moment a group is picked, so the group
 * itself never learns that no direction was chosen. This hook runs when `tableGrouping` is
 * updated from the browser: if the GROUP changed (not just the direction) and the new group
 * is a SummaryGroup with a default direction, that direction is applied.
 */
class ApplyDefaultGroupDirection extends ComponentHook
{
    public function update(string $propertyName, string $fullPath, mixed $newValue): ?Closure
    {
        $component = $this->component;

        if ($propertyName !== 'tableGrouping' || !$component instanceof HasTable || !property_exists($component, 'tableGrouping')) {
            return null;
        }

        $previous = $component->tableGrouping;

        return function () use ($component, $previous): void {
            $current = $component->tableGrouping;

            if (!is_string($current) || $current === '') {
                return;
            }

            $groupId = (string) str($current)->before(':');

            if ($groupId === (string) str((string) $previous)->before(':')) {
                return;
            }

            $group = $component->getTable()->getGroup($groupId);

            if (!$group instanceof SummaryGroup || ($direction = $group->getDefaultDirection()) === null) {
                return;
            }

            $component->tableGrouping = $groupId.':'.$direction;

            if ($component->getTable()->persistsGroupInSession() && method_exists($component, 'getTableGroupingSessionKey')) {
                session()->put($component->getTableGroupingSessionKey(), $component->tableGrouping);
            }
        };
    }
}
