<?php

namespace HeimrichHannot\FlareBundle\EventListener\QueryStructModifier;

use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 480)]
class GroupByModifierListener
{
    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        if ($event->config->isCounting)
        {
            $event->queryStruct->setGroupBy(null);
        }
    }
}