<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;

final class FilterTest extends TestCase
{
    public function testElementUnionAccessors(): void
    {
        $typed = new Filter(element: 'flare_bool');

        self::assertSame('flare_bool', $typed->getElementType());
        self::assertNull($typed->getElementInstance());

        $instance = $this->createInlineElement();
        $inline = new Filter(element: $instance);

        self::assertNull($inline->getElementType());
        self::assertSame($instance, $inline->getElementInstance());
    }

    public function testWithersPreserveOtherFields(): void
    {
        $filter = new Filter(element: 'test', config: ['a' => 1], alias: 'foo', source: 'tl_flare_filter.1');

        $withData = $filter->withData(['value' => 42]);

        self::assertNull($filter->data);
        self::assertSame(['value' => 42], $withData->data);
        self::assertSame('foo', $withData->alias);
        self::assertSame(['a' => 1], $withData->config);
        self::assertSame('tl_flare_filter.1', $withData->source);

        $targeted = $filter->withTargetAlias('translation');

        self::assertSame('translation', $targeted->targetAlias);
        self::assertTrue($targeted->targetingForced);
        self::assertFalse($filter->targetingForced);
    }

    public function testFingerprintRepresentsInlineElementsByClass(): void
    {
        $instance = $this->createInlineElement();
        $filter = new Filter(element: $instance);

        self::assertSame($instance::class, $filter->fingerprint()['element']);
    }

    private function createInlineElement(): FilterElementInterface
    {
        return new class implements FilterElementInterface {
            public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
            {
            }

            public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
            {
            }
        };
    }
}
