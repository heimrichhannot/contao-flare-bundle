<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\ListCompound;
use Symfony\Contracts\EventDispatcher\Event;

class ReaderSchemaOrgEvent extends Event
{
    public function __construct(
        public readonly ListCompound $list,
        public readonly Entry        $entry,
        public array                 $data = [],
    ) {}
}
