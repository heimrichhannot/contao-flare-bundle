<?php

namespace HeimrichHannot\FlareBundle\Generator;

use Contao\Model;
use Contao\PageModel;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\ReaderLinkableInterface;
use HeimrichHannot\FlareBundle\Event\DetailsPageUrlGeneratedEvent;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ReaderPageUrlGenerator
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function generate(PageModel $readerPage, Model $model, string $autoItemField): ?string
    {
        if (!$autoItem = (string) CallbackHelper::tryGetProperty($model, $autoItemField)) {
            return null;
        }

        $url = $readerPage->getAbsoluteUrl('/' . $autoItem);

        $event = $this->eventDispatcher->dispatch(
            new DetailsPageUrlGeneratedEvent(
                model: $model,
                autoItem: $autoItem,
                page: $readerPage,
                url: $url,
            )
        );

        return $event->getUrl();
    }

    /**
     * @param ReaderLinkableInterface $config
     * @return callable(Model $model): ?string
     */
    public function createCallable(ReaderLinkableInterface $config): callable
    {
        if (!$page = $config->getJumpToReaderPage()) {
            return static fn (Model $model): ?string => null;
        }

        return fn (Model $model): ?string => $this->generate($page, $model, $config->getAutoItemField());
    }
}