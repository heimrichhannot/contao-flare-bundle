<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoNews\EventListener;

use Contao\News;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryModelFactory;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
class NewsReaderSchemaOrgListener
{
    public function __construct(
        private readonly EntryModelFactory $entryModelFactory,
    ) {}

    public function __invoke(ReaderSchemaOrgEvent $event): void
    {
        if ($event->entry->meta->modelRef?->table !== NewsModel::getTable()) {
            return;
        }

        $model = $this->entryModelFactory->create($event->entry);

        if (!$model instanceof NewsModel) {
            return;
        }

        $event->data = News::getSchemaOrgData($model);
    }
}
