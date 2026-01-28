<?php

namespace HeimrichHannot\FlareBundle\Context\Factory;

use HeimrichHannot\FlareBundle\Context\AggregationConfig;
use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;

class AggregationConfigFactory
{
    public function createFromConfig(ContextConfigInterface $config): AggregationConfig
    {
        return new AggregationConfig();
    }
}