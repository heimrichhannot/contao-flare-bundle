<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\EventListener;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Events;
use Contao\Model;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
class EventsSchemaOrgListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
    ) {}

    /**
     * Loads the record through the finder of the table's registered model class, so a multilingual model class
     * translates it; a class that does not extend CalendarEventsModel yields no data.
     */
    public function __invoke(ReaderSchemaOrgEvent $event): void
    {
        $ref = $event->entry->meta->modelRef;

        if ($ref?->table !== CalendarEventsModel::getTable()) {
            return;
        }

        $class = $this->framework->getAdapter(Model::class)->getClassFromTable($ref->table);
        $model = $this->framework->getAdapter($class)->findById($ref->id);

        if (!$model instanceof CalendarEventsModel) {
            return;
        }

        $event->data = $this->framework->getAdapter(Events::class)->getSchemaOrgData($model);
    }
}
