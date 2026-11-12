<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Reader\BackLink;

/**
 * @implements ProjectorInterface<ValidationView>
 */
class ValidationProjector extends AbstractProjector
{
    public function supports(ListCompound $list, IntentInterface $intent): bool
    {
        return $intent instanceof ValidationIntent;
    }

    public function project(ListCompound $list, IntentInterface $intent): ValidationView
    {
        \assert($intent instanceof ValidationIntent, '$intent must be an instance of ValidationIntent');

        $loader = $this->createLoader(new ValidationLoaderConfig(
            list: $list,
            intent: $intent,
            autoItemField: $intent->autoItemField,
        ));

        $entryCollectorFactory = $this->getEntryCollectorFactory();
        $readerUrlConfig = $intent->createReaderUrlConfig();
        $readerUrlGenerator = $this->getReaderUrlGeneratorFactory()->create($readerUrlConfig);
        $collector = $entryCollectorFactory->create($list, $intent, $readerUrlGenerator);
        $lookup = $entryCollectorFactory->createLookup($list, $intent, $readerUrlGenerator);
        $backLink = $intent->createBackLink();

        return $this->createView(loader: $loader, collector: $collector, lookup: $lookup, backLink: $backLink);
    }

    protected function createLoader(ValidationLoaderConfig $config): ValidationLoaderInterface
    {
        return $this->getLoaderFactory()->createValidationLoader($config);
    }

    protected function createView(
        ValidationLoaderInterface $loader,
        EntryCollector            $collector,
        EntryLookupInterface      $lookup,
        ?BackLink                 $backLink = null,
    ): ValidationView {
        return new ValidationView(
            loader: $loader,
            collector: $collector,
            lookup: $lookup,
            backLink: $backLink,
        );
    }
}
