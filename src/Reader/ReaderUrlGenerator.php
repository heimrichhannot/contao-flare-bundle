<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Event\ReaderUrlGeneratedEvent;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ReaderUrlGenerator implements ReaderUrlGeneratorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ReaderUrlConfig          $config,
    ) {}

    /**
     * Reads the auto-item value from the entry's row; an empty value has no link.
     */
    public function generate(Entry $entry): ?string
    {
        $autoItem = (string) ($entry->row[$this->config->autoItemField] ?? '');

        if ($autoItem === '') {
            return null;
        }

        // The stored value is Contao-input-encoded; the URL text is its inverse, and the router percent-encodes it.
        $autoItem = StringUtil::revertInputEncoding($autoItem);
        $url = $this->config->readerPage->getAbsoluteUrl('/' . $autoItem);

        $event = $this->eventDispatcher->dispatch(
            new ReaderUrlGeneratedEvent(
                entry: $entry,
                autoItem: $autoItem,
                page: $this->config->readerPage,
                url: $url,
            )
        );

        return $event->url;
    }
}
