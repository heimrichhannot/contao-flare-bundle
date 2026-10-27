<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/** An element without any port. */
class ProviderStubElement implements FilterElementInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {}
}
