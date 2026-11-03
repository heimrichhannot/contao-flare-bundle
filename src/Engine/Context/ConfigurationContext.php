<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

/**
 * The list is being configured in the backend: filters are evaluated for their options,
 * but never queried.
 */
final readonly class ConfigurationContext implements ContextInterface
{
    public static function getContextType(): string
    {
        return 'configuration';
    }
}
