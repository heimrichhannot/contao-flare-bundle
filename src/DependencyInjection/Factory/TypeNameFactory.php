<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Factory;

use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\DependencyInjection\Container;

class TypeNameFactory
{
    public static function createFilterElementType(string $className): string
    {
        $className = \ltrim(\strrchr($className, '\\') ?: $className, '\\');
        $className = Str::trimSubstrings($className, suffix: ['Controller', 'FilterElement', 'Element']);

        return Container::underscore($className);
    }
}