<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value\Choice;

/**
 * A choice identified by a plain field value; it has no scope.
 */
final readonly class ValueChoice implements ChoiceObjectInterface
{
    public function __construct(
        public string  $value,
        public ?string $label = null,
    ) {}

    public function getChoiceScope(): ?string
    {
        return null;
    }

    public function getChoiceLocalKey(): string
    {
        return $this->value;
    }

    public function getLabelParameters(): array
    {
        return ['%value%' => $this->value];
    }
}
