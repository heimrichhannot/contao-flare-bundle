<?php

namespace HeimrichHannot\FlareBundle\Filter;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;

class FilterQueryBuilder
{
    private array $conditions = [];
    private array $parameters = [];
    private array $types = [];
    private bool $blocking = false;

    public function __construct(
        private readonly ExpressionBuilder $expr,
        private readonly string $alias,
    ) {}

    public function expr(): ExpressionBuilder
    {
        return $this->expr;
    }

    public function alias(): string
    {
        return $this->alias;
    }

    public function isBlocking(): bool
    {
        return $this->blocking;
    }

    public function blockList(): void
    {
        $this->blocking = true;
    }

    public function unblockList(): void
    {
        $this->blocking = false;
    }

    public function clear(): void
    {
        $this->conditions = [];
        $this->parameters = [];
        $this->types = [];
    }

    public function where(string|CompositeExpression $query, ?array $params = null): static
    {
        $this->conditions[] = $query;

        foreach ($params ?? [] as $key => $value) {
            $this->bind($key, $value);
        }

        return $this;
    }

    public function bind(string $param, string|int|array $value, int $type = null): static
    {
        $param = \ltrim($param, ':');

        if (!\preg_match('/^[a-zA-Z0-9_]+$/', $param)) {
            throw new \InvalidArgumentException(\sprintf('Invalid parameter name: "%s"', $param));
        }

        if (\is_array($value) && empty($value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid parameter value for "%s": arrays must not be empty', $param));
        }

        $type ??= $this->tryGetPDOType($value);

        if (!\is_int($type)) {
            throw new \InvalidArgumentException(\sprintf('PDO-Parameter type for "%s" cannot be guessed.', $param));
        }

        $this->parameters[$param] = $value;
        $this->types[$param] = $type;

        return $this;
    }

    protected function tryGetPDOType(mixed $value): ?int
    {
        if (\is_null($value)) {
            return ParameterType::NULL;
        }

        if (\is_int($value)) {
            return ParameterType::INTEGER;
        }

        if (\is_bool($value)) {
            return ParameterType::BOOLEAN;
        }

        if (\is_scalar($value))
            // float and string remain of the scalar types (bool and int are already checked)
        {
            return ParameterType::STRING;
        }

        if (\is_array($value))
        {
            $allInt = true;
            $allStr = true;

            foreach ($value as $v)
            {
                if ($notInt = !\is_int($v)) {
                    $allInt = false;
                }
                // Allowed: scalar, null, object with __toString method
                if ($notStr = !(\is_scalar($v) || \is_null($v) || (\is_object($v) && \method_exists($v, '__toString')))) {
                    $allStr = false;
                }

                if ($notInt && $notStr) {
                    return null;
                }
            }

            if ($allInt) {
                return ArrayParameterType::INTEGER;
            }

            if ($allStr) {
                return ArrayParameterType::STRING;
            }

            return null;
        }

        return null;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $this->bind($key, $value);
        }
    }

    public function buildQuery(?string $prefix): array
    {
        if (empty($this->conditions)) {
            return ['', [], []];
        }

        $cond = $this->expr->and(...$this->conditions);
        $sql = (string) $cond;

        if ($prefix === null) {
            return [$sql, $this->parameters, $this->types];
        }

        $prefix = '_' . \trim($prefix, '_') . '_';
        $parameters = [];
        $types = [];

        $sql = preg_replace_callback(
            '/:([A-Za-z0-9_]+)\b/',
            function ($matches) use ($prefix, &$parameters, &$types)
            {
                $paramName = $matches[1];

                if (isset($this->parameters[$paramName]))
                {
                    $uniqueName = $prefix . $paramName;

                    $parameters[$uniqueName] = $this->parameters[$paramName];
                    $types[$uniqueName] = $this->types[$paramName];

                    return ':' . $uniqueName;
                }

                return $matches[0];
            },
            $sql
        );

        // $parameters = \array_map(static fn($p) => \is_array($p) ? Str::sqlSerialize($p) : $p, $parameters);

        return [$sql, $parameters, $types];
    }
}