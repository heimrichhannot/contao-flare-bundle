<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterEffect;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;

/** An element without any port. */
class ProviderStubElement implements FilterElementInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterEffect $effect): void {}

    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementScope                 $scope,
        ?ValueInterface              $value,
    ): void {}
}
