<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Enum;

/**
 * Which logical values a checkbox's two states mean (SPEC_FILTER_FORMS §7.5): unchecked is "no filter" or false,
 * checked is always true.
 */
enum BoolBinaryChoices: string
{
    case NULL_TRUE = 'null_true';
    case TRUE_FALSE = 'true_false';

    public static function asOptions(): array
    {
        return [
            self::NULL_TRUE->value => 'flare.bool_binary_choices.null_true',
            self::TRUE_FALSE->value => 'flare.bool_binary_choices.true_false',
        ];
    }
}
