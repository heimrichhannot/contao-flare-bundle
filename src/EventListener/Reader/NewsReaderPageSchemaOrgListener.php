<?php

namespace HeimrichHannot\FlareBundle\EventListener\Ŗeader;

use Contao\News;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Event\ReaderPageSchemaOrgEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
class NewsReaderPageSchemaOrgListener
{
    public function __invoke(ReaderPageSchemaOrgEvent $event): void
    {
        $model = $event->model;

        if (!$model instanceof NewsModel) {
            return;
        }

        $event->data = News::getSchemaOrgData($model);
    }
}