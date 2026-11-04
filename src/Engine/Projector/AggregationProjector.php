<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Intent\AggregationIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\AggregationView;
use HeimrichHannot\FlareBundle\List\ListCompound;

/**
 * @implements ProjectorInterface<AggregationView>
 */
class AggregationProjector extends AbstractProjector
{
    public function supports(ListCompound $list, IntentInterface $intent): bool
    {
        return $intent instanceof AggregationIntent;
    }

    public function project(ListCompound $list, IntentInterface $intent): AggregationView
    {
        \assert($intent instanceof AggregationIntent, '$intent must be an instance of AggregationIntent');

        $loader = $this->createLoader(new AggregationLoaderConfig(
            list: $list,
            intent: $intent,
            filterValues: $intent->getFilterValues(),
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
