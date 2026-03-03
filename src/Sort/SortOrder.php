<?php

namespace HeimrichHannot\FlareBundle\Sort;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Util\Str;

final readonly class SortOrder implements \Serializable, \Stringable
{
    public const ASC = 'ASC';
    public const DESC = 'DESC';

    private function __construct(
        private string $alias,
        private string $column,
        private string $direction = self::ASC,
    ) {}

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getQualifiedColumn(): string
    {
        return $this->alias . '.' . $this->column;
    }

    public function key(): string
    {
        return $this->getQualifiedColumn();
    }

    public function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'column' => $this->column,
            'direction' => $this->direction,
        ];
    }

    public function serialize(): ?string
    {
        return \serialize($this->__serialize());
    }

    /**
     * @throws FlareException
     */
    public function unserialize(string $data): void
    {
        $data = \unserialize($data, ['allowed_classes' => false]);
        $this->__unserialize($data);
    }

    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @throws FlareException If validation fails
     */
    public function __unserialize(array $data): void
    {
        $alias = $data['alias'] ?? null;
        $column = $data['column'] ?? null;
        $direction = $data['direction'] ?? null;

        if (!$alias && \str_contains($column, '.')) {
            [$alias, $column] = \explode('.', $column, 2);
        }

        self::validate($alias, $column, $direction);

        $this->alias = $alias;
        $this->column = $column;
        $this->direction = $direction;
    }

    public function __toString(): string
    {
        return $this->getQualifiedColumn() . ' ' . $this->direction;
    }

    /**
     * @throws FlareException If validation fails
     */
    public static function of(string $alias, string $column, string $direction = self::ASC): self
    {
        $alias = \trim($alias, "` \n\r\t\v\0");
        $column = \trim($column, "` \n\r\t\v\0");
        $direction = \strtoupper($direction);

        self::validate($alias, $column, $direction);

        return new self($alias, $column, $direction);
    }

    /**
     * @throws FlareException If validation fails
     */
    public function fromQualified(string $qualified, string $direction = self::ASC): self
    {
        $parts = \explode('.', \trim($qualified), 2);

        if (\count($parts) !== 2) {
            throw new \InvalidArgumentException('Qualified name must contain exactly one dot.');
        }

        return self::of($parts[0], $parts[1], $direction);
    }

    /**
     * @throws FlareException If validation fails
     */
    private static function validate(?string $alias, ?string $column, ?string $direction): void
    {
        $errors = [];

        if (!Str::isValidSqlName($alias)) {
            $errors[] = 'Alias must not be empty or contain invalid characters.';
        }

        if (!Str::isValidSqlName($column)) {
            $errors[] = 'Column must not be empty or contain invalid characters.';
        }

        if (!$direction || !in_array($direction, [self::ASC, self::DESC], true)) {
            $errors[] = 'Direction must be either ASC or DESC.';
        }

        if ($errors) {
            throw new FlareException(\implode(' ', $errors), method: __METHOD__);
        }
    }
}