<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Factory;

use HeimrichHannot\FlareBundle\Util\Str;
use function Symfony\Component\String\u;

final readonly class TypeNameFactory
{
    private static function createType(string $className, array $suffixes): string
    {
        $shortName = \basename(\str_replace('\\', '/', $className));
        $trimmedName = Str::trimSubstrings($shortName, suffix: $suffixes);

        return u($trimmedName)->snake()->toString();
    }

    public static function createFilterElementType(string $className): string
    {
        return self::createType($className, ['Controller', 'FilterElement', 'Element']);
    }

    public static function createListDriverType(string $className): string
    {
        return self::createType($className, ['Controller', 'ListDriver', 'Driver']);
    }
}
