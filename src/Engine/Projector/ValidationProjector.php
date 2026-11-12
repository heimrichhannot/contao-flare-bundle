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

        return $this->createView(
            loader: $loader,
            table: $list->dc,
            autoItemField: $intent->autoItemField,
            backLink: $intent->createBackLink(),
        );
    }

    protected function createLoader(ValidationLoaderConfig $config): ValidationLoaderInterface
    {
        return $this->getLoaderFactory()->createValidationLoader($config);
    }

    protected function createView(
        ValidationLoaderInterface $loader,
        string                    $table,
        string                    $autoItemField,
        ?BackLink                 $backLink = null,
    ): ValidationView {
        return new ValidationView(
            loader: $loader,
            table: $table,
            autoItemField: $autoItemField,
            backLink: $backLink,
        );
    }
}
