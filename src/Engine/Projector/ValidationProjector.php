<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Engine\Factory\LoaderFactory;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Reader\Factory\ReaderUrlGeneratorFactory;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<ValidationView>
 */
class ValidationProjector extends AbstractProjector
{
    public function __construct(
        private readonly LoaderFactory             $loaderFactory,
        private readonly ReaderUrlGeneratorFactory $readerUrlGeneratorFactory,
    ) {}

    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return $context instanceof ValidationContext;
    }

    public function project(ListSpecification $list, ContextInterface $context): ValidationView
    {
        \assert($context instanceof ValidationContext, '$config must be an instance of ValidationConfig');

        $readerUrlConfig = $context->createReaderUrlConfig();
        $autoItemField = $readerUrlConfig->autoItemField;

        $loader = $this->createLoader(new ValidationLoaderConfig(
            list: $list,
            context: $context,
            autoItemField: $autoItemField,
        ));

        $readerUrlGenerator = $this->readerUrlGeneratorFactory->create($readerUrlConfig);

        return $this->createView(
            loader: $loader,
            readerUrlGenerator: $readerUrlGenerator,
            table: $list->dc,
            autoItemField: $autoItemField,
        );
    }

    protected function createLoader(ValidationLoaderConfig $config): ValidationLoaderInterface
    {
        return $this->loaderFactory->createValidationLoader($config);
    }

    protected function createView(
        ValidationLoaderInterface   $loader,
        ReaderUrlGeneratorInterface $readerUrlGenerator,
        string                      $table,
        string                      $autoItemField,
    ): ValidationView {
        return new ValidationView(
            loader: $loader,
            readerUrlGenerator: $readerUrlGenerator,
            table: $table,
            autoItemField: $autoItemField,
        );
    }
}