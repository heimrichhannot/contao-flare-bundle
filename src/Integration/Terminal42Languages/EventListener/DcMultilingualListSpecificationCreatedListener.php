<?php

namespace HeimrichHannot\FlareBundle\Integration\Terminal42Languages\EventListener;

use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use HeimrichHannot\FlareBundle\Integration\Terminal42Languages\ListType\DcMultilingualListType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class DcMultilingualListSpecificationCreatedListener
{
    public function __invoke(ListSpecificationCreatedEvent $event): void
    {
        $list = $event->listSpecification;

        if ($list->type === DcMultilingualListType::TYPE) {
            $list->isPageMetaGeneric = true;
        }
    }
}