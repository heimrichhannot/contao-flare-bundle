<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use PHPUnit\Framework\TestCase;

final class ListSpecTest extends TestCase
{
    private static function driver(): ListDriverInterface
    {
        static $driver = null;

        return $driver ??= new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return (string) ($config['dc'] ?? '');
            }
        };
    }

    private static function filter(string $type, ?string $alias = null): Filter
    {
        static $element = null;

        $element ??= new class implements FilterElementInterface {
            public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

            public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void {}
        };

        return new Filter(element: $element, type: $type, alias: $alias);
    }

    private static function spec(array $config = [], ?string $source = null): ListSpec
    {
        return new ListSpec(
            driver: self::driver(),
            type: 'test_list',
            dc: 'tl_test',
            config: $config,
            source: $source,
        );
    }

    public function testWithFilterKeysByAliasByDefault(): void
    {
        $spec = self::spec()->withFilter(self::filter('flare_bool', 'foo'));

        self::assertArrayHasKey('foo', $spec->filters);
    }

    public function testWithFilterAcceptsExplicitKey(): void
    {
        $spec = self::spec()->withFilter(self::filter('flare_bool', 'foo'), 'custom');

        self::assertArrayHasKey('custom', $spec->filters);
        self::assertArrayNotHasKey('foo', $spec->filters);
    }

    public function testWithFilterGeneratesCollisionFreeKeysForAliasLessFilters(): void
    {
        $spec = self::spec()
            ->withFilter(self::filter('a'))
            ->withFilter(self::filter('b'));

        self::assertArrayHasKey('_generated_0', $spec->filters);
        self::assertArrayHasKey('_generated_1', $spec->filters);

        $spec = $spec->withoutFilter('_generated_0')->withFilter(self::filter('c'));

        self::assertSame('c', $spec->filters['_generated_0']->type);
        self::assertSame('b', $spec->filters['_generated_1']->type);
    }

    public function testModifiersAreImmutable(): void
    {
        $original = self::spec(config: ['id' => 1]);

        $modified = $original->withFilter(self::filter('a', 'x'));

        self::assertSame([], $original->filters);
        self::assertNotSame($original, $modified);
        self::assertSame(['id' => 1], $modified->config);
        self::assertArrayHasKey('x', $modified->filters);
    }

    public function testHasFilterInstance(): void
    {
        $spec = self::spec()
            ->withFilter(new Filter(element: new StubFilterElement(), type: 'stub', alias: 'p'));

        self::assertTrue($spec->hasFilterInstance(StubFilterElement::class));
        self::assertTrue($spec->hasFilterInstance(FilterElementInterface::class));
        self::assertFalse($spec->hasFilterInstance(PublishedFilterElement::class));
    }

    public function testHashIsStableAndChangesWithContent(): void
    {
        $make = static fn (array $config = [], ?string $source = null): ListSpec =>
            self::spec(config: $config, source: $source);

        self::assertSame($make()->hash(), $make()->hash());
        self::assertNotSame($make()->hash(), $make(config: ['id' => 1])->hash());
        self::assertNotSame($make()->hash(), $make(source: 'tl_flare_list.5')->hash());
        self::assertNotSame(
            $make()->hash(),
            $make()->withFilter(self::filter('a', 'x'))->hash(),
        );
    }
}
