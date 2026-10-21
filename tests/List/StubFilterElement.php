<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

class StubFilterElement implements FilterElementInterface
{
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}
}
