<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use Symfony\Contracts\EventDispatcher\Event;

class ReaderUrlGeneratedEvent extends Event
{
    /**
     * @param string $autoItem The auto-item as URL text: the stored value with Contao's input encoding reverted,
     *                         never percent-encoded. A listener that builds another URL from it passes it to the
     *                         router as is.
     */
    public function __construct(
        public readonly Entry $entry,
        public string         $autoItem,
        public PageModel      $page,
        public string         $url,
    ) {}
}
