<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * Reads the value class a filter control produces from the narrowed return type of a method.
 */
final class ValueClassReflector
{
    /**
     * @param class-string $class
     * @return class-string<ValueInterface>
     * @throws \InvalidArgumentException If the return type is not a single concrete value class.
     */
    public static function fromReturnType(string $class, string $method): string
    {
        if (!\class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist.', $class));
        }

        if (!\method_exists($class, $method)) {
            throw new \InvalidArgumentException(\sprintf('Method %s::%s() does not exist.', $class, $method));
        }

        $type = (new \ReflectionMethod($class, $method))->getReturnType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin())
        {
            throw new \InvalidArgumentException(\sprintf(
                '%s::%s() must declare a single concrete value class as its return type (nullable is allowed),'
                . ' got "%s".',
                $class,
                $method,
                $type ?? 'none',
            ));
        }

        $valueClass = $type->getName();

        if ($valueClass === ValueInterface::class)
        {
            throw new \InvalidArgumentException(\sprintf(
                '%s::%s() must narrow its return type to one concrete class; a bare ValueInterface'
                . ' does not identify a value class.',
                $class,
                $method,
            ));
        }

        if (!\is_a($valueClass, ValueInterface::class, true))
        {
            throw new \InvalidArgumentException(\sprintf(
                '%s::%s() returns "%s", which does not implement %s.',
                $class,
                $method,
                $valueClass,
                ValueInterface::class,
            ));
        }

        return $valueClass;
    }
}
