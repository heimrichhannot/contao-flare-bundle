<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Factory;

use HeimrichHannot\FlareBundle\Util\Str;
use function Symfony\Component\String\u;

class TypeNameFactory
{
    public static function createFilterElementType(string $className): string
    {
        $shortName = \basename(\str_replace('\\', '/', $className));
        $trimmedName = Str::trimSubstrings($shortName, suffix: ['Controller', 'FilterElement', 'Element']);

        return u($trimmedName)->snake()->toString();
    }
}