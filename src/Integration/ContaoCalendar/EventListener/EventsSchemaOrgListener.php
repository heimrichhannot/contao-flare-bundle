<?php

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\EventListener;

use Contao\CalendarEventsModel;
use Contao\Events;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
class EventsSchemaOrgListener
{
    public function __invoke(ReaderSchemaOrgEvent $event): void
    {
        $model = $event->model;

        if (!$model instanceof CalendarEventsModel) {
            return;
        }

        $event->data = Events::getSchemaOrgData($model);
    }
}