<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent;

interface IntentInterface
{
    /**
     * Returns the unique machine name of this intent type (e.g., 'interactive').
     */
    public static function getIntentType(): string;
}
