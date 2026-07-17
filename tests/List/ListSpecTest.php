<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
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
            public function getDataContainerName(array $config): string
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

    public function testDataContainerNameComesFromConfig(): void
    {
        $spec = new ListSpec(driver: self::driver(), config: ['dc' => 'tl_test']);

        self::assertSame('tl_test', $spec->getDataContainerName());
        self::assertSame('', (new ListSpec(driver: self::driver()))->getDataContainerName());
    }

    public function testWithFilterKeysByAliasByDefault(): void
    {
        $spec = new ListSpec(driver: self::driver());

        $spec = $spec->withFilter(self::filter('flare_bool', 'foo'));

        self::assertArrayHasKey('foo', $spec->filters);
    }

    public function testWithFilterAcceptsExplicitKey(): void
    {
        $spec = (new ListSpec(driver: self::driver()))
            ->withFilter(self::filter('flare_bool', 'foo'), 'custom');

        self::assertArrayHasKey('custom', $spec->filters);
        self::assertArrayNotHasKey('foo', $spec->filters);
    }

    public function testWithFilterGeneratesCollisionFreeKeysForAliasLessFilters(): void
    {
        $spec = (new ListSpec(driver: self::driver()))
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
        $original = new ListSpec(driver: self::driver(), config: ['id' => 1]);

        $modified = $original
            ->withFilter(self::filter('a', 'x'))
            ->withConfig(['id' => 2]);

        self::assertSame([], $original->filters);
        self::assertSame(['id' => 1], $original->config);
        self::assertNotSame($original, $modified);
        self::assertSame(['id' => 2], $modified->config);
        self::assertArrayHasKey('x', $modified->filters);
    }

    public function testHasFilterOfType(): void
    {
        $spec = (new ListSpec(driver: self::driver()))
            ->withFilter(self::filter('flare_published', 'p'));

        self::assertTrue($spec->hasFilterOfType('flare_published'));
        self::assertFalse($spec->hasFilterOfType('flare_bool'));
    }

    public function testHashIsStableAndChangesWithContent(): void
    {
        $make = static fn (array $config = [], ?string $source = null): ListSpec =>
            new ListSpec(driver: self::driver(), config: $config, source: $source);

        self::assertSame($make()->hash(), $make()->hash());
        self::assertNotSame($make()->hash(), $make(config: ['id' => 1])->hash());
        self::assertNotSame($make()->hash(), $make(source: 'tl_flare_list.5')->hash());
        self::assertNotSame(
            $make()->hash(),
            $make()->withFilter(self::filter('a', 'x'))->hash(),
        );
    }
}
