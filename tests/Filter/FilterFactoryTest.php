<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use PHPUnit\Framework\TestCase;

final class FilterFactoryTest extends TestCase
{
    private static function element(): FilterElementInterface
    {
        return new class implements FilterElementInterface {
            public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

            public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void {}
        };
    }

    public function testCreatesFromRegisteredTypeAlias(): void
    {
        $element = self::element();

        $registry = new FilterElementRegistry();
        $registry->add($element, null, 'my_element');

        $filter = (new FilterFactory($registry))->create(
            element: 'my_element',
            config: ['a' => 1],
            alias: 'foo',
        );

        self::assertSame($element, $filter->element);
        self::assertSame('my_element', $filter->type);
        self::assertSame(['a' => 1], $filter->config);
        self::assertSame('foo', $filter->alias);
    }

    public function testCreatesFromInstanceWithoutType(): void
    {
        $element = self::element();

        $filter = (new FilterFactory(new FilterElementRegistry()))->create(element: $element);

        self::assertSame($element, $filter->element);
        self::assertNull($filter->type);
    }

    public function testThrowsForUnknownTypeAlias(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('Filter element type "missing" not found');

        (new FilterFactory(new FilterElementRegistry()))->create(element: 'missing');
    }
}
