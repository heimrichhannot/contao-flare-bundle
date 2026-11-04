<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/** An element without any port. */
class ProviderStubElement implements FilterElementInterface
{
    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {}
}
