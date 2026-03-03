<?php

namespace HeimrichHannot\FlareBundle\Util;

class BeActionsHelper
{
    public const OP_CHILDREN = 'children';
    public const OP_EDIT = 'edit';
    public const OPERATIONS = [
        self::OP_CHILDREN,
        self::OP_EDIT,
    ];

    public static function operation(string $operation, ?string $itemTable = null): array
    {
        $contao4 = Env::isContao4();

        if (!\in_array($operation, self::OPERATIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid operation: %s', $operation));
        }

        $key = match ($operation) {
            self::OP_CHILDREN => $contao4 ? 'edit' : 'children',
            self::OP_EDIT => $contao4 ? 'editheader' : 'edit',
        };

        $val = match ($operation) {
            self::OP_CHILDREN => [
                'href' => "table=$itemTable",
                'icon' => $contao4 ? 'edit.svg' : 'children.svg',
            ],
            self::OP_EDIT => [
                'href' => 'act=edit',
                'icon' => $contao4 ? 'header.svg' : 'edit.svg',
            ],
        };

        return [$key => $val];
    }
}