<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Inspection\Stub;

use HeimrichHannot\FlareBundle\Contract\FilterElement\IneffectiveFilterContract;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Restricts nothing without a control and a value.
 */
final class IneffectiveElement implements FilterElementInterface, IneffectiveFilterContract
{
    public function getIneffectiveReason(FilterCompound $filter): ?TranslatableInterface
    {
        if ($filter->control !== null || $filter->value !== null) {
            return null;
        }

        return new TranslatableMessage('stub.ineffective', domain: 'flare');
    }

    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {}
}
