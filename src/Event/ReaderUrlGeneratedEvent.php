<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use Symfony\Contracts\EventDispatcher\Event;

class ReaderUrlGeneratedEvent extends Event
{
    public function __construct(
        public readonly Entry $entry,
        public string         $autoItem,
        public PageModel      $page,
        public string         $url,
    ) {}
}
