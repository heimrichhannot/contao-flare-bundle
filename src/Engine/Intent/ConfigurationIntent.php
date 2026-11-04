<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent;

/**
 * The list is being configured in the backend: filters are evaluated for their options,
 * but never queried.
 */
final readonly class ConfigurationIntent implements IntentInterface
{
    public static function getIntentType(): string
    {
        return 'configuration';
    }
}
