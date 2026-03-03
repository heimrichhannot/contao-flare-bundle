<?php

namespace HeimrichHannot\FlareBundle\Filter;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Util\SqlHelper;

class FilterQueryBuilder
{
    private ExpressionBuilder $expr;
    private array $conditions = [];
    private array $parameters = [];
    private array $types = [];

    /**
     * @param Connection $connection The DBAL Connection.
     * @param string $alias The unquoted alias of the table to be filtered.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SqlHelper  $sqlHelper,
        private readonly string     $alias,
    ) {}

    public function expr(): ExpressionBuilder
    {
        return $this->expr ??= $this->connection->createExpressionBuilder();
    }

    public function alias(): string
    {
        return $this->alias;
    }

    /**
     * Quotes an identifier (e.g., table or column name) for use in a SQL query.
     *
     * @deprecated Use {@see self::column()} instead.
     */
    public function quoteIdentifier(string $value): string
    {
        return $this->connection->quoteIdentifier($value);
    }

    /**
     * Quotes a column identifier, ensuring that it is properly attached to the current table alias.
     *
     * Required for joins and subqueries to work properly.
     *
     * @param string $column The column name to quote.
     * @return string The quoted column identifier.
     * @throws FilterException If the column name contains invalid characters.
     */
    public function column(string $column): string
    {
        if (!\preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new FilterException('Invalid column name: only alphanumeric characters and underscores are allowed.');
        }

        return $this->connection->quoteIdentifier($this->alias() . '.' . $column);
    }

    /**
     * Aborts the filtering process.
     *
     * This method is typically used when a certain condition is met that requires
     * the filtering to stop and return an empty result set.
     *
     * @noinspection RedundantSuppression, PhpDocMissingThrowsInspection
     * @throws null {@see AbortFilteringException}
     */
    public static function abort(): never
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        throw new AbortFilteringException;
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
            $this->setParameter($key, $value);
        }

        return $this;
    }

    public function whereAnd(string|CompositeExpression ...$conditions): static
    {
        if (!$conditions) {
            return $this;
        }

        $this->conditions[] = $this->expr()->and(...$conditions);

        return $this;
    }

    public function whereOr(string|CompositeExpression ...$conditions): static
    {
        if (!$conditions) {
            return $this;
        }

        $this->conditions[] = $this->expr()->or(...$conditions);

        return $this;
    }

    public function whereInSerialized(array|int|string $find, string $column): static
    {
        return $this->where($this->sqlHelper->findInSerializedArrayColumn($find, $this->column($column)));
    }

    public function setParameter(string $param, string|int|array $value, ?int $type = null): static
    {
        $param = \ltrim($param, ':');

        if (!\preg_match('/^[a-zA-Z0-9_]+$/', $param)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid parameter name "%s": alphanumeric characters and underscores only.', $param,
            ));
        }

        if (\is_array($value) && !$value) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid parameter value for "%s": arrays must not be empty.', $param,
            ));
        }

        $type ??= $this->tryGetPDOType($value);

        if (!\is_int($type)) {
            throw new \InvalidArgumentException(\sprintf(
                'PDO parameter type of "%s" cannot be guessed. Please provide a type explicitly.', $param,
            ));
        }

        $this->parameters[$param] = $value;
        $this->types[$param] = $type;

        return $this;
    }

    public function reset(): void
    {
        $this->conditions = [];
        $this->parameters = [];
        $this->types = [];
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

        if (\is_array($value) && $value)
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
            $this->setParameter($key, $value);
        }
    }

    public function build(?string $prefix): FilterQuery
    {
        if (!$this->conditions) {
            return new FilterQuery('', [], []);
        }

        $cond = $this->expr()->and(...$this->conditions);
        $sql = (string) $cond;

        if ($prefix === null) {
            return new FilterQuery($sql, $this->parameters, $this->types);
        }

        if (!\preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid prefix "%s": alphanumeric characters and underscores only.', $prefix,
            ));
        }

        $prefix = '_' . \trim($prefix, '_') . '_';
        $parameters = [];
        $types = [];

        $sql = \preg_replace_callback(
            '/:([A-Za-z0-9_]+)\b/',
            function (array $matches) use ($prefix, &$parameters, &$types): string
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
            $sql,
        );

        return new FilterQuery($sql, $parameters, $types);
    }
}