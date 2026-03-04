<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View;

use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;

class InteractiveEventsView extends InteractiveView
{
    private array $entriesGrouped;

    public function getEntriesGrouped(): array
    {
        if (isset($this->entriesGrouped)) {
            return $this->entriesGrouped;
        }

        $byDate = [];

        foreach ($this->getEntries() as $entry) {
            $byDate[$entry['_flare_event_group'] ?? ''][] = $entry;
        }

        return $this->entriesGrouped = $byDate;
    }
}