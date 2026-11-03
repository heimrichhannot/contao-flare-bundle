<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Filter\Filter;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Optional port for elements that can be configured into restricting nothing. The backend marks such a filter in the
 * filter list and explains the reason on its edit form; the runtime behavior is unaffected.
 *
 * @api
 */
interface IneffectiveFilterContract
{
    /**
     * Why the filter restricts nothing as configured, or null if it does restrict.
     */
    public function getIneffectiveReason(Filter $filter): ?TranslatableInterface;
}
