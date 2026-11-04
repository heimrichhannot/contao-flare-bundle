<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Reader\BackLink;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;

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

        $readerUrlConfig = $intent->createReaderUrlConfig();
        $autoItemField = $readerUrlConfig->autoItemField ?? $intent->autoItemField;

        $loader = $this->createLoader(new ValidationLoaderConfig(
            list: $list,
            intent: $intent,
            autoItemField: $autoItemField,
        ));

        $readerUrlGenerator = $this->getReaderUrlGeneratorFactory()->create($readerUrlConfig);

        return $this->createView(
            loader: $loader,
            readerUrlGenerator: $readerUrlGenerator,
            table: $list->dc,
            autoItemField: $autoItemField,
            backLink: $intent->createBackLink(),
        );
    }

    protected function createLoader(ValidationLoaderConfig $config): ValidationLoaderInterface
    {
        return $this->getLoaderFactory()->createValidationLoader($config);
    }

    protected function createView(
        ValidationLoaderInterface   $loader,
        ReaderUrlGeneratorInterface $readerUrlGenerator,
        string                      $table,
        string                      $autoItemField,
        ?BackLink                   $backLink = null,
    ): ValidationView {
        return new ValidationView(
            loader: $loader,
            readerUrlGenerator: $readerUrlGenerator,
            table: $table,
            autoItemField: $autoItemField,
            backLink: $backLink,
        );
    }
}
