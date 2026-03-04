<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\EventListener;

use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
readonly class DoNotPaginateModifierListener
{
    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        if (!($event->config->attributes['ContaoCalendar_doNotPaginate'] ?? false)) {
            return;
        }

        $event->queryStruct->setLimit(null)->setOffset(null);
    }
}