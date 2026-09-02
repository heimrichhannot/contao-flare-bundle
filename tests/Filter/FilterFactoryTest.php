<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class FilterFactoryTest extends TestCase
{
    private static function factory(?FilterElementRegistry $registry = null): FilterFactory
    {
        return new FilterFactory(
            $registry ?? new FilterElementRegistry(),
            new FilterTransformerResolver(new EventDispatcher()),
        );
    }

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

        $filter = self::factory($registry)->create(
            element: 'my_element',
            config: ['a' => 1],
            alias: 'foo',
        );

        self::assertSame($element, $filter->element);
        self::assertSame('my_element', $filter->type);
        self::assertSame(['a' => 1], $filter->config);
        self::assertSame('foo', $filter->alias);
    }

    public function testCreatesFromInstanceUsingItsClassNameAsType(): void
    {
        $element = self::element();

        $filter = self::factory()->create(element: $element);

        self::assertSame($element, $filter->element);
        self::assertSame(\get_class($element), $filter->type);
    }

    public function testThrowsForUnknownTypeAlias(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('Filter element type "missing" not found');

        self::factory()->create(element: 'missing');
    }
}
