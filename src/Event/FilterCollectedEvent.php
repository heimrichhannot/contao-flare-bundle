<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched for every filter collected from the database, before it is added
 * to the list. Listeners may replace the filter.
 */
class FilterCollectedEvent extends Event
{
    public function __construct(
        public FilterCompound       $filter,
        public readonly FilterModel $model,
    ) {}
}
