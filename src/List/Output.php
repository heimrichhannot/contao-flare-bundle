<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

/**
 * A place a list is rendered for, to which a filter can be made exclusive
 * ({@see \HeimrichHannot\FlareBundle\Filter\FilterCompound::$outputs}).
 *
 * @api
 */
enum Output: string
{
    case ListView = 'list_view';
}
