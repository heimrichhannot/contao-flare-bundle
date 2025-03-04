<?php

namespace HeimrichHannot\FlareBundle\Filter;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;

class FilterQueryBuilder
{
    private array $conditions = [];
    private array $parameters = [];

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

    public function where(string|CompositeExpression $query, ?array $params = null): static
    {
        $this->conditions[] = $query;

        foreach ($params ?? [] as $key => $value) {
            $this->bind($key, $value);
        }

        return $this;
    }

    public function bind(string $param, mixed $value): static
    {
        $param = \ltrim($param, ':');

        if (!\preg_match('/^[a-zA-Z0-9_]+$/', $param)) {
            throw new \InvalidArgumentException('Invalid parameter name');
        }

        $this->parameters[$param] = $value;

        return $this;
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
        $cond = $this->expr->and(...$this->conditions);
        $sql = (string) $cond;

        if ($prefix === null) {
            return [$sql, $this->parameters];
        }

        $prefix = '_' . \trim($prefix, '_') . '_';
        $parameters = [];

        $sql = preg_replace_callback(
            '/:([A-Za-z0-9_]+)\b/',
            function ($matches) use ($prefix, &$parameters)
            {
                $paramName = $matches[1];

                if (isset($this->parameters[$paramName]))
                {
                    $parameters[$prefix . $paramName] = $this->parameters[$paramName];
                    return ':' . $prefix . $paramName;
                }

                return $matches[0];
            },
            $sql
        );

        return [$sql, $parameters];
    }
}