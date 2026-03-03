<?php

namespace HeimrichHannot\FlareBundle\SortDescriptor;

final class Order
{
    public const ASC = 'ASC';
    public const DESC = 'DESC';

    public function __construct(
        private string $column,
        private string $direction = self::ASC,
    ) {
        $this->column = \trim($this->column);

        if (!$this->column) {
            throw new \InvalidArgumentException('Column must not be empty.');
        }

        $this->direction = \strtoupper($this->direction);
        if (!in_array($this->direction, [self::ASC, self::DESC], true)) {
            throw new \InvalidArgumentException('Direction must be either ASC or DESC.');
        }
    }

    public static function of(string $property, string $direction = self::ASC): self
    {
        return new self($property, $direction);
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }
}