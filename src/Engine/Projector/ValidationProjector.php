<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Reader\BackLink;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;

/**
 * @implements ProjectorInterface<ValidationView>
 */
class ValidationProjector extends AbstractProjector
{
    public function supports(ListSpec $list, ContextInterface $context): bool
    {
        return $context instanceof ValidationContext;
    }

    public function project(ListSpec $list, ContextInterface $context): ValidationView
    {
        \assert($context instanceof ValidationContext, '$config must be an instance of ValidationConfig');

        $readerUrlConfig = $context->createReaderUrlConfig();
        $autoItemField = $readerUrlConfig->autoItemField ?? $context->autoItemField;

        $loader = $this->createLoader(new ValidationLoaderConfig(
            list: $list,
            context: $context,
            autoItemField: $autoItemField,
        ));

        $readerUrlGenerator = $this->getReaderUrlGeneratorFactory()->create($readerUrlConfig);

        return $this->createView(
            loader: $loader,
            readerUrlGenerator: $readerUrlGenerator,
            table: $list->dc,
            autoItemField: $autoItemField,
            backLink: $context->createBackLink(),
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
