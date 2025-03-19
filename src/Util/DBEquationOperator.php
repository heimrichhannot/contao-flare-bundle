<?php

namespace HeimrichHannot\FlareBundle\Util;

enum DBEquationOperator: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUALS = '>=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUALS = '<=';
    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case IS_NULL = 'IS NULL';
    case IS_NOT_NULL = 'IS NOT NULL';

    public static function asOptions(bool $includeIn = true): array
    {
        $cases = DBEquationOperator::cases();

        if ($includeIn) {
            unset($cases[DBEquationOperator::IN->name]);
            unset($cases[DBEquationOperator::NOT_IN->name]);
        }

        return \array_change_key_case(\array_combine(
            \array_map(static fn($case) => $case->name, $cases),
            \array_map(static fn($case) => $case->value, $cases)
        ), \CASE_LOWER);  // lowercase keys
    }

    public static function match(DBEquationOperator|string $operator): ?DBEquationOperator
    {
        if (\is_string($operator))
        {
            if ($from = DBEquationOperator::tryFrom($operator)) {
                return $from;
            }

            foreach (self::cases() as $case) {
                if (\strtoupper($case->name) === \strtoupper($operator)) {
                    return $case;
                }
            }

            return null;
        }

        return $operator;
    }
}