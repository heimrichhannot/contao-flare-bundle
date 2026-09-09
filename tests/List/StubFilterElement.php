<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FormulaBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

class StubFilterElement implements FilterElementInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}
}
