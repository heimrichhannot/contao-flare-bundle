<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;

class AggregationContextFactory
{
    public function createFromConfig(ContextInterface $config): AggregationContext
    {
        /**
         * This could be used to create an AggregationContext from a given configuration.
         * I.e.
         * ```php
         * $var = match($config::class) {
         *     InteractiveConfig::class => $config->getVar(),
         *     default => null,
         * }
         * ```
         */

        return new AggregationContext();
    }
}