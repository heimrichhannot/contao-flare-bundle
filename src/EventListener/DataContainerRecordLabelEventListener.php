<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use Contao\CoreBundle\Event\DataContainerRecordLabelEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class DataContainerRecordLabelEventListener
{
    public function __invoke(DataContainerRecordLabelEvent $event): void
    {
        if (!str_starts_with($event->getIdentifier(), 'contao.db.tl_flare_list.')) {
            return;
        }

        if (''=== ((string)$event->getData()['title'] ?? '')) {
            return;
        }

        $event->setLabel($event->getData()['title']. ' (ID '.$event->getData()['id'].')');
    }
}
