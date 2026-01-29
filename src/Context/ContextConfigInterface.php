<?php

namespace HeimrichHannot\FlareBundle\Context;

interface ContextConfigInterface
{
    /**
     * Returns the unique machine name of this context type (e.g., 'interactive').
     */
    public static function getContextType(): string;
}