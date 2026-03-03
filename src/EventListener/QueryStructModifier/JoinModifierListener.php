<?php

namespace HeimrichHannot\FlareBundle\EventListener\QueryStructModifier;

use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: -450)]
class JoinModifierListener
{
    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        $resolvedJoins = $event->tableAliasRegistry->resolveActiveJoins();
        $event->queryStruct->setJoins($resolvedJoins);
    }
}