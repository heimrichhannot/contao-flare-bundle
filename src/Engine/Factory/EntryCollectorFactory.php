<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Factory;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryLookup;
use HeimrichHannot\FlareBundle\Engine\ListEntry\LazyEntryLookup;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\Entry\EntryTaggerInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Reader\Factory\ReaderUrlGeneratorFactory;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates the collectors that turn a list's rows into entries, and the lookups of a list's member entries.
 */
final readonly class EntryCollectorFactory
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private LoaderFactory             $loaderFactory,
        private ReaderUrlGeneratorFactory $readerUrlGeneratorFactory,
        private EntryTaggerInterface      $entryTagger,
    ) {}

    /**
     * A collector for the rows of $list loaded for $intent; its entries link to the reader through
     * $readerUrlGenerator.
     */
    public function create(
        ListCompound                $list,
        IntentInterface             $intent,
        ReaderUrlGeneratorInterface $readerUrlGenerator,
    ): EntryCollector {
        return new EntryCollector($this->eventDispatcher, $list, $intent, $readerUrlGenerator->generate(...));
    }

    /**
     * Membership by the reader's rules: a validation loader for $list and $intent with the reader's auto-item field,
     * the reader URL generator of $intent (or the given one, built for the same reader page and auto-item field), a
     * collector for $intent. Built lazily, on the first lookup.
     */
    public function createLookup(
        ListCompound                 $list,
        ValidationIntent             $intent,
        ?ReaderUrlGeneratorInterface $readerUrlGenerator = null,
    ): EntryLookupInterface {
        return new LazyEntryLookup(function () use ($list, $intent, $readerUrlGenerator): EntryLookup {
            $loader = $this->loaderFactory->createValidationLoader(new ValidationLoaderConfig(
                list: $list,
                intent: $intent,
                autoItemField: $intent->autoItemField,
            ));
            $readerUrlGenerator ??= $this->readerUrlGeneratorFactory->create($intent->createReaderUrlConfig());

            return new EntryLookup($loader, $this->create($list, $intent, $readerUrlGenerator), $this->entryTagger);
        });
    }
}
