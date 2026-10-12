<?php

declare(strict_types=1);


namespace HeimrichHannot\FlareBundle\EventListener;

use Contao\CoreBundle\Event\DataContainerRecordLabelEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class DataContainerRecordLabelEventListener
{
    public function __invoke(DataContainerRecordLabelEvent $event): void
    {
        if (!\str_starts_with($event->getIdentifier(), 'contao.db.tl_flare_list.')) {
            return;
        }

        $data = $event->getData();
        $id = (int) ($data['id'] ?? 0);
        $title = \trim((string) ($data['title'] ?? ''));

        if (!$id || $title === '') {
            return;
        }

        $event->setLabel(\sprintf('%s (ID %s)', $title, $id));
    }
}
