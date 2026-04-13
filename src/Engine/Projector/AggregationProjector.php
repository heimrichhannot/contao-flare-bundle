<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Factory\LoaderFactory;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\AggregationView;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<AggregationView>
 */
class AggregationProjector extends AbstractProjector
{
    public function __construct(
        private readonly LoaderFactory $loaderFactory,
    ) {}

    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return $context instanceof AggregationContext;
    }

    public function project(ListSpecification $list, ContextInterface $context): AggregationView
    {
        \assert($context instanceof AggregationContext, '$config must be an instance of AggregationConfig');

        $loader = $this->createLoader(new AggregationLoaderConfig(
            list: $list,
            context: $context,
            filterValues: $this->gatherFilterValues($list, $context->getFilterValues()),
        ));

        return $this->createView($loader);
    }

    protected function createLoader(AggregationLoaderConfig $config): AggregationLoaderInterface
    {
        return $this->loaderFactory->createAggregationLoader($config);
    }

    protected function createView(AggregationLoaderInterface $loader): AggregationView
    {
        return new AggregationView(loader: $loader);
    }
}