<?php

namespace HeimrichHannot\FlareBundle\Enum;

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
            self::IS_NULL, self::IS_NOT_NULL => true,
            default => false,
        };
    }

    public function getOperator(): string
    {
        return match ($this) {
            self::EQUALS => '=',
            self::NOT_EQUALS => '!=',
            self::GREATER_THAN => '>',
            self::GREATER_THAN_EQUALS => '>=',
            self::LESS_THAN => '<',
            self::LESS_THAN_EQUALS => '<=',
            self::LIKE => 'LIKE',
            self::NOT_LIKE => 'NOT LIKE',
            self::IN => 'IN',
            self::NOT_IN => 'NOT IN',
            self::IS_NULL => 'IS NULL',
            self::IS_NOT_NULL => 'IS NOT NULL',
        };
    }

    public static function asOptions(bool $includeIn = true): array
    {
        $cases = self::cases();

        if (!$includeIn)
        {
            $cases = \array_filter($cases, static fn(SqlEquationOperator $case): bool => !\in_array($case, [
                SqlEquationOperator::IN,
                SqlEquationOperator::NOT_IN
            ], strict: true));
        }

        return \array_combine(
            \array_map(static fn(SqlEquationOperator $case): string => $case->value, $cases),
            \array_map(static fn(SqlEquationOperator $case): string => $case->getOperator(), $cases)
        );
    }

    public static function match(SqlEquationOperator|string $operator): ?SqlEquationOperator
    {
        if ($operator instanceof self) {
            return $operator;
        }

        if ($from = self::tryFrom($operator)) {
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