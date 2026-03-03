<?php

namespace HeimrichHannot\FlareBundle\EventListener\Reader;

use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use HeimrichHannot\FlareBundle\ListType\GenericDataContainerListType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class AttachGenericPageMetaListener
{
    public function __invoke(ListSpecificationCreatedEvent $event): void
    {
        $list = $event->listSpecification;

        if ($list->type === GenericDataContainerListType::TYPE) {
            $list->isPageMetaGeneric = true;
        }
    }
}