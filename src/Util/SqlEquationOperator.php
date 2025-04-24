<?php

namespace HeimrichHannot\FlareBundle\Util;

enum SqlEquationOperator: string
{
    case EQUALS = 'eq';
    case NOT_EQUALS = 'neq';
    case GREATER_THAN = 'gt';
    case GREATER_THAN_EQUALS = 'gte';
    case LESS_THAN = 'lt';
    case LESS_THAN_EQUALS = 'lte';
    case LIKE = 'like';
    case NOT_LIKE = 'unlike';
    case IN = 'in';
    case NOT_IN = 'ni';
    case IS_NULL = 'null';
    case IS_NOT_NULL = 'notnull';

    public function isUnary(): bool
    {
        return match ($this) {
            SqlEquationOperator::IS_NULL, SqlEquationOperator::IS_NOT_NULL => true,
            default => false,
        };
    }

    public function getOperator(): string
    {
        return match ($this) {
            SqlEquationOperator::EQUALS => '=',
            SqlEquationOperator::NOT_EQUALS => '!=',
            SqlEquationOperator::GREATER_THAN => '>',
            SqlEquationOperator::GREATER_THAN_EQUALS => '>=',
            SqlEquationOperator::LESS_THAN => '<',
            SqlEquationOperator::LESS_THAN_EQUALS => '<=',
            SqlEquationOperator::LIKE => 'LIKE',
            SqlEquationOperator::NOT_LIKE => 'NOT LIKE',
            SqlEquationOperator::IN => 'IN',
            SqlEquationOperator::NOT_IN => 'NOT IN',
            SqlEquationOperator::IS_NULL => 'IS NULL',
            SqlEquationOperator::IS_NOT_NULL => 'IS NOT NULL',
        };
    }

    public static function asOptions(bool $includeIn = true): array
    {
        $cases = SqlEquationOperator::cases();

        if (!$includeIn) {
            $cases = \array_filter($cases, static fn($case) => !\in_array($case, [SqlEquationOperator::IN, SqlEquationOperator::NOT_IN]));
        }

        return \array_combine(
            \array_map(static fn($case) => $case->value, $cases),
            \array_map(static fn($case) => $case->getOperator(), $cases)
        );
    }

    public static function match(SqlEquationOperator|string $operator): ?SqlEquationOperator
    {
        if ($operator instanceof SqlEquationOperator) {
            return $operator;
        }

        if ($from = SqlEquationOperator::tryFrom($operator)) {
            return $from;
        }

        foreach (self::cases() as $case) {
            if (\strtoupper($case->name) === \strtoupper($operator)) {
                return $case;
            }
        }

        return null;
    }
}