<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Factory;

use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoader;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoader;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoader;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;

final readonly class LoaderFactory
{
    public function __construct(
        private ListQueryDirector $listQueryDirector,
    ) {}

    public function createAggregationLoader(AggregationLoaderConfig $config): AggregationLoader
    {
        return new AggregationLoader(
            config: $config,
            listQueryDirector: $this->listQueryDirector,
        );
    }

    public function createInteractiveLoader(InteractiveLoaderConfig $config): InteractiveLoader
    {
        return new InteractiveLoader(
            config: $config,
            listQueryDirector: $this->listQueryDirector,
        );
    }

    public function createValidationLoader(ValidationLoaderConfig $config): ValidationLoader
    {
        return new ValidationLoader(
            config: $config,
            listQueryDirector: $this->listQueryDirector,
        );
    }
}