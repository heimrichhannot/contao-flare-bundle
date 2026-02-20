<?php

namespace HeimrichHannot\FlareBundle\Query;

enum JoinTypeEnum: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
}