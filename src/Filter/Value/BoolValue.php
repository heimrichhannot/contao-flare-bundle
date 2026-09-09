<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;

final readonly class BoolValue implements ValueInterface
{
    public function __construct(
        public bool $state,
    ) {}

    /**
     * @param mixed $value Raw submitted or configured value.
     * @param BoolBinaryChoices|null $choices Binary-choice variant, or null for no collapse.
     */
    public static function tryFrom(mixed $value, ?BoolBinaryChoices $choices = null): ?self
    {
        if (\is_string($value)) {
            $value = \strtolower(\trim($value));
        }

        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        if ($choices === BoolBinaryChoices::NULL_TRUE && !$value) {
            return null;
        }

        $state = \filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);

        return $state === null ? null : new self($state);
    }
}
