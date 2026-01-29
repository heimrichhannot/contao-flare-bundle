<?php

namespace HeimrichHannot\FlareBundle\Context;

class AggregationConfig implements ContextConfigInterface
{
    public static function getContextType(): string
    {
        return 'aggregation';
    }
}