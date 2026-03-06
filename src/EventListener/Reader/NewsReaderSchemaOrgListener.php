<?php

namespace HeimrichHannot\FlareBundle\EventListener\Reader;

use Contao\News;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
class NewsReaderSchemaOrgListener
{
    public function __invoke(ReaderSchemaOrgEvent $event): void
    {
        $model = $event->model;

        if (!$model instanceof NewsModel) {
            return;
        }

        $event->data = News::getSchemaOrgData($model);
    }
}