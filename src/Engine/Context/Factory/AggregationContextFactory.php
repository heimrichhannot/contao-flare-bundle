<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;

class AggregationContextFactory
{
    public function createFromConfig(ContextInterface $config): AggregationContext
    {
        return new AggregationContext();
    }
}