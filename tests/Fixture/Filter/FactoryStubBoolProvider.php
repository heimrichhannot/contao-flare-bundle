<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Fixture\Filter;

use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\Contao\DcaContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;

final class FactoryStubBoolProvider implements ValueProviderInterface
{
    public function provide(mixed $stored, FilterElementInterface $element): ?BoolValue
    {
        return match ($stored) {
            null, '' => null,
            '1' => new BoolValue(true),
            '0' => new BoolValue(false),
            'plain' => throw new FilterException('plain'),
            'bug' => throw new \InvalidArgumentException('a bug in the provider'),
            'wiring' => throw new FlareException('a wiring error'),
            default => throw new UnreadableValueException('unreadable'),
        };
    }

    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void {}
}
