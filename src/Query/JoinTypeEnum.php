<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query;

enum JoinTypeEnum: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
}