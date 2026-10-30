<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

final readonly class BoolValue implements ValueInterface
{
    public function __construct(
        public bool $state,
    ) {}

    /**
     * @param mixed $value Raw submitted or configured value.
     */
    public static function tryFrom(mixed $value): ?self
    {
        if (\is_string($value)) {
            $value = \strtolower(\trim($value));
        }

        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        $state = \filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);

        return $state === null ? null : new self($state);
    }

    public function fingerprint(): string
    {
        return $this->state ? '1' : '0';
    }
}
