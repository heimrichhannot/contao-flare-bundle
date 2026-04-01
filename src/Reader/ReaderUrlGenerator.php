<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use Contao\Model;
use HeimrichHannot\FlareBundle\Event\DetailsPageUrlGeneratedEvent;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ReaderUrlGenerator implements ReaderUrlGeneratorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ReaderUrlConfig $config,
    ) {}

    public function generate(Model $model): ?string
    {
        if (!$autoItem = (string) CallbackHelper::tryGetProperty($model, $this->config->autoItemField)) {
            return null;
        }

        $url = $this->config->readerPage->getAbsoluteUrl('/' . $autoItem);

        $event = $this->eventDispatcher->dispatch(
            new DetailsPageUrlGeneratedEvent(
                model: $model,
                autoItem: $autoItem,
                page: $this->config->readerPage,
                url: $url,
            )
        );

        return $event->getUrl();
    }
}