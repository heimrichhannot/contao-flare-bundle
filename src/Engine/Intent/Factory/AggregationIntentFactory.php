<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent\Factory;

use HeimrichHannot\FlareBundle\Engine\Intent\AggregationIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;

final class AggregationIntentFactory
{
    public function createFromIntent(IntentInterface $intent): AggregationIntent
    {
        /**
         * This could be used to create an AggregationIntent from a given intent.
         * I.e.
         * ```php
         * $var = match($intent::class) {
         *     InteractiveIntent::class => $intent->getVar(),
         *     default => null,
         * }
         * ```
         */

        return new AggregationIntent();
    }
}
