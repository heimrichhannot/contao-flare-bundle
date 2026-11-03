<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;

final readonly class KeywordsValueProvider implements ValueProviderInterface
{
    public function provide(mixed $stored, FilterElementInterface $element): ?KeywordsValue
    {
        return KeywordsValue::tryFrom($stored);
    }

    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void
    {
        $field
            ->inputType('text')
            ->eval(['maxlength' => 255, 'tl_class' => 'w50']);
    }
}
