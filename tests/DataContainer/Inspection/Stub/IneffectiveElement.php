<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub;

use HeimrichHannot\FlareBundle\Contract\FilterElement\IneffectiveFilterContract;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Restricts nothing without a control and a value.
 */
final class IneffectiveElement implements FilterElementInterface, IneffectiveFilterContract
{
    public function getIneffectiveReason(Filter $filter): ?TranslatableInterface
    {
        if ($filter->control !== null || $filter->value !== null) {
            return null;
        }

        return new TranslatableMessage('stub.ineffective', domain: 'flare');
    }

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {}
}
