<?php

namespace HeimrichHannot\FlareBundle\Engine\Context;

interface ContextInterface
{
    /**
     * Returns the unique machine name of this context type (e.g., 'interactive').
     */
    public static function getContextType(): string;
}