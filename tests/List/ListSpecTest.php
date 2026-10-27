<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\FilterFailure;
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

    private static function filter(string $type, string $alias): Filter
    {
        static $element = null;

        return new Filter(
            element: new ElementBinding($element ??= new StubFilterElement(), $type, new ComponentConfig([])),
            alias: $alias,
        );
    }

    /**
     * @param list<FilterFailure> $failures
     */
    private static function spec(array $config = [], ?string $source = null, array $failures = []): ListSpec
    {
        return new ListSpec(
            driver: self::driver(),
            type: 'test_list',
            dc: 'tl_test',
            config: $config,
            source: $source,
            failures: $failures,
        );
    }

    public function testWithFilterKeysByAliasByDefault(): void
    {
        $spec = self::spec()->withFilter(self::filter('flare_bool', 'foo'));

        self::assertArrayHasKey('foo', $spec->filters);
    }

    public function testWithFilterRejectsADuplicateAlias(): void
    {
        $spec = self::spec()
            ->withFilter(self::filter('a', 'foo'))
            ->withFilter(self::filter('b', 'bar'));

        self::assertSame(['foo', 'bar'], \array_keys($spec->filters));
        self::assertSame(['bar'], \array_keys($spec->withoutFilter('foo')->filters));

        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('"foo"');

        $spec->withFilter(self::filter('c', 'foo'));
    }

    /**
     * @dataProvider customKeyCases
     */
    public function testWithFilterRejectsAnAliasTakenUnderACustomKey(string $key, string $alias): void
    {
        $spec = self::spec()->withFilters([$key => self::filter('a', $alias)]);

        $this->expectException(FlareException::class);

        $spec->withFilter(self::filter('b', 'foo'));
    }

    public static function customKeyCases(): iterable
    {
        yield 'existing alias under another key' => ['custom', 'foo'];
        yield 'another alias under the alias as key' => ['foo', 'bar'];
    }

    public function testAFilterIsReplacedByRemovingItFirst(): void
    {
        $spec = self::spec()
            ->withFilter(self::filter('a', 'foo'))
            ->withoutFilter('foo')
            ->withFilter(self::filter('c', 'foo'));

        self::assertSame('c', $spec->filters['foo']->element->type);
    }

    public function testWithoutFilterRemovesByElementClass(): void
    {
        $published = new Filter(
            element: new ElementBinding(new PublishedFilterElement(), 'published', new ComponentConfig([])),
            alias: 'p',
        );

        $spec = self::spec()
            ->withFilter($published)
            ->withFilter(self::filter('stub', 'foo'))
            ->withoutFilter(PublishedFilterElement::class);

        self::assertSame(['foo'], \array_keys($spec->filters));
    }

    public function testFailuresSurviveWithers(): void
    {
        $first = new FilterFailure('tl_flare_filter.1', 'broken');
        $second = new FilterFailure('tl_flare_filter.2', 'broken');
        $spec = self::spec(failures: [$first])->withFailure($second);

        $expected = [$first, $second];

        self::assertSame($expected, $spec->failures);
        self::assertSame($expected, $spec->withFilter(self::filter('a', 'x'))->failures);
        self::assertSame($expected, $spec->withoutFilter('x')->failures);
        self::assertSame($expected, $spec->withFilters([])->failures);
        self::assertNotSame(self::spec()->hash(), self::spec(failures: [new FilterFailure(null, 'broken')])->hash());
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
            ->withFilter(new Filter(
                element: new ElementBinding(new StubFilterElement(), 'stub', new ComponentConfig([])),
                alias: 'p',
            ));

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
