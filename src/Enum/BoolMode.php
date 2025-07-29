<?php

namespace HeimrichHannot\FlareBundle\Enum;

enum BoolMode: string
{
    case BINARY = 'binary';
    case TERNARY = 'ternary';

    public function isBinary(): bool
    {
        return $this === self::BINARY;
    }

    public function isTernary(): bool
    {
        return $this === self::TERNARY;
    }

    public static function asOptions(): array
    {
        return [
            self::BINARY->value => 'flare.bool_mode.binary',
            self::TERNARY->value => 'flare.bool_mode.ternary',
        ];
    }
}