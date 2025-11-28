<?php

namespace HeimrichHannot\FlareBundle\Enum;

enum FetchSubject: string
{
    case IDS = 'ids';
    case ENTRIES = 'entries';
    case COUNT = 'count';
}