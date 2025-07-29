<?php

namespace HeimrichHannot\FlareBundle\Enum;

enum BoolBinaryChoices: string
{
    case NULL_TRUE = 'null_true';
    case NULL_FALSE = 'null_false';
    case TRUE_FALSE = 'true_false';

    public function hasNull(): bool
    {
        return $this === self::NULL_TRUE || $this === self::NULL_FALSE;
    }

    public function hasTrue(): bool
    {
        return $this === self::NULL_TRUE || $this === self::TRUE_FALSE;
    }

    public function hasFalse(): bool
    {
        return $this === self::NULL_FALSE || $this === self::TRUE_FALSE;
    }

    public static function asOptions(): array
    {
        return [
            self::NULL_TRUE->value => 'flare.bool_binary_choices.null_true',
            self::NULL_FALSE->value => 'flare.bool_binary_choices.null_false',
            self::TRUE_FALSE->value => 'flare.bool_binary_choices.true_false',
        ];
    }
}