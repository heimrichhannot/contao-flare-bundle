<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\AggregationView;
use HeimrichHannot\FlareBundle\List\ListSpec;

/**
 * @implements ProjectorInterface<AggregationView>
 */
class AggregationProjector extends AbstractProjector
{
    public function supports(ListSpec $list, ContextInterface $context): bool
    {
        return $context instanceof AggregationContext;
    }

    public function project(ListSpec $list, ContextInterface $context): AggregationView
    {
        \assert($context instanceof AggregationContext, '$config must be an instance of AggregationConfig');

        $loader = $this->createLoader(new AggregationLoaderConfig(
            list: $list,
            context: $context,
            filterValues: $context->getFilterValues(),
        ));

        return $this->createView($loader);
    }

    protected function createLoader(AggregationLoaderConfig $config): AggregationLoaderInterface
    {
        return $this->getLoaderFactory()->createAggregationLoader($config);
    }

    protected function createView(AggregationLoaderInterface $loader): AggregationView
    {
        return new AggregationView(loader: $loader);
    }
}
