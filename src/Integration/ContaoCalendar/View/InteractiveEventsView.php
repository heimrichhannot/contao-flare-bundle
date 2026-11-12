<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View;

use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\List\Entry\Entry;

class InteractiveEventsView extends InteractiveView
{
    /** @var array<string, list<Entry>> */
    private array $entriesGrouped;

    /**
     * The entries of the page grouped by their row's `_flare_event_group` ('' without one). An event occurring on
     * several days is one entry per occurrence.
     *
     * @return array<string, list<Entry>>
     */
    public function getEntriesGrouped(): array
    {
        if (isset($this->entriesGrouped)) {
            return $this->entriesGrouped;
        }

        $byGroup = [];

        foreach ($this->getEntries() as $entry) {
            $byGroup[$entry->row['_flare_event_group'] ?? ''][] = $entry;
        }

        return $this->entriesGrouped = $byGroup;
    }
}
