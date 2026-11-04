<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Inspection\Stub;

use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

final class OkElement implements FilterElementInterface
{
    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
        $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
    }
}
