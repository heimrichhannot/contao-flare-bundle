<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * Runtime check for parameters that cannot be narrowed by the type system (contravariance).
 */
trait ExpectsValueTrait
{
    /**
     * @template T of ValueInterface
     * @param class-string<T> $class
     * @return T|null Null passes through.
     * @throws FilterException If the value is of another class.
     */
    protected function expectValue(?ValueInterface $value, string $class): ?ValueInterface
    {
        if ($value === null || $value instanceof $class) {
            return $value;
        }

        throw new FilterException(\sprintf(
            '%s expects a value of class %s, got %s.',
            static::class,
            $class,
            $value::class,
        ));
    }
}
