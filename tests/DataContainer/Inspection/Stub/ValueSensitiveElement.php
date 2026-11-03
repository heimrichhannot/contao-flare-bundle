<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

final class ValueSensitiveElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        if ($value !== null) {
            throw new FilterException('value rejected');
        }
    }
}
