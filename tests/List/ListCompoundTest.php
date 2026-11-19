<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\List\Output;
use PHPUnit\Framework\TestCase;

final class ListCompoundTest extends TestCase
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

    /**
     * @param list<Output>|null $outputs
     */
    private static function filter(string $type, string $alias, ?array $outputs = null): FilterCompound
    {
        static $element = null;

        return new FilterCompound(
            element: new ElementBinding($element ??= new StubFilterElement(), $type, new ComponentConfig([]), null),
            alias: $alias,
            outputs: $outputs,
        );
    }

    /**
     * @param list<FilterFailure> $failures
     */
    private static function list(array $config = [], ?string $source = null, array $failures = []): ListCompound
    {
        return new ListCompound(
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
        $list = self::list()->withFilter(self::filter('flare_bool', 'foo'));

        self::assertArrayHasKey('foo', $list->filters);
    }

    public function testWithFilterKeepsNumericAliasKeys(): void
    {
        // Filters without an explicit alias are keyed by their row id, which PHP stores as an integer key.
        $list = self::list()
            ->withFilters(['12' => self::filter('a', '12'), '15' => self::filter('b', '15')])
            ->withFilter(self::filter('c', '0'));

        self::assertSame([12, 15, 0], \array_keys($list->filters));
    }

    public function testWithFilterRejectsADuplicateAlias(): void
    {
        $list = self::list()
            ->withFilter(self::filter('a', 'foo'))
            ->withFilter(self::filter('b', 'bar'));

        self::assertSame(['foo', 'bar'], \array_keys($list->filters));
        self::assertSame(['bar'], \array_keys($list->withoutFilter('foo')->filters));

        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('"foo"');

        $list->withFilter(self::filter('c', 'foo'));
    }

    /**
     * @dataProvider customKeyCases
     */
    public function testWithFilterRejectsAnAliasTakenUnderACustomKey(string $key, string $alias): void
    {
        $list = self::list()->withFilters([$key => self::filter('a', $alias)]);

        $this->expectException(FlareException::class);

        $list->withFilter(self::filter('b', 'foo'));
    }

    public static function customKeyCases(): iterable
    {
        yield 'existing alias under another key' => ['custom', 'foo'];
        yield 'another alias under the alias as key' => ['foo', 'bar'];
    }

    public function testAFilterIsReplacedByRemovingItFirst(): void
    {
        $list = self::list()
            ->withFilter(self::filter('a', 'foo'))
            ->withoutFilter('foo')
            ->withFilter(self::filter('c', 'foo'));

        self::assertSame('c', $list->filters['foo']->element->type);
    }

    public function testWithoutFilterRemovesByElementClass(): void
    {
        $published = new FilterCompound(
            element: new ElementBinding(new PublishedFilterElement(), 'published', new ComponentConfig([]), null),
            alias: 'p',
        );

        $list = self::list()
            ->withFilter($published)
            ->withFilter(self::filter('stub', 'foo'))
            ->withoutFilter(PublishedFilterElement::class);

        self::assertSame(['foo'], \array_keys($list->filters));
    }

    public function testFailuresSurviveWithers(): void
    {
        $first = new FilterFailure('tl_flare_filter.1', 'broken');
        $second = new FilterFailure('tl_flare_filter.2', 'broken');
        $list = self::list(failures: [$first])->withFailure($second);

        $expected = [$first, $second];

        self::assertSame($expected, $list->failures);
        self::assertSame($expected, $list->withFilter(self::filter('a', 'x'))->failures);
        self::assertSame($expected, $list->withoutFilter('x')->failures);
        self::assertSame($expected, $list->withFilters([])->failures);
    }

    public function testModifiersAreImmutable(): void
    {
        $original = self::list(config: ['id' => 1]);

        $modified = $original->withFilter(self::filter('a', 'x'));

        self::assertSame([], $original->filters);
        self::assertNotSame($original, $modified);
        self::assertSame(['id' => 1], $modified->config);
        self::assertArrayHasKey('x', $modified->filters);
    }

    public function testHasFilterInstance(): void
    {
        $list = self::list()
            ->withFilter(new FilterCompound(
                element: new ElementBinding(new StubFilterElement(), 'stub', new ComponentConfig([]), null),
                alias: 'p',
            ));

        self::assertTrue($list->hasFilterInstance(StubFilterElement::class));
        self::assertTrue($list->hasFilterInstance(FilterElementInterface::class));
        self::assertFalse($list->hasFilterInstance(PublishedFilterElement::class));
    }

    /**
     * @param array<string, mixed> $config
     * @param list<FilterFailure> $failures
     */
    private static function mixed(array $config = [], array $failures = []): ListCompound
    {
        return self::list(config: $config, failures: $failures)->withFilters([
            'a' => self::filter('stub', 'a'),
            'b' => self::filter('stub', 'b', [Output::ListView]),
            'c' => self::filter('stub', 'c', []),
        ]);
    }

    public function testWholeDropsExclusiveFilters(): void
    {
        self::assertSame(['a'], \array_keys(self::mixed()->whole()->filters));
    }

    public function testForOutputKeepsTheFiltersOfThatOutput(): void
    {
        self::assertSame(['a', 'b'], \array_keys(self::mixed()->forOutput(Output::ListView)->filters));
    }

    public function testDerivedListsKeepFailuresAndConfig(): void
    {
        $failure = new FilterFailure('tl_flare_filter.1', 'broken');
        $list = self::mixed(['x' => 1], [$failure]);

        foreach ([$list->whole(), $list->forOutput(Output::ListView)] as $derived) {
            self::assertNotSame($list, $derived);
            self::assertSame($list->failures, $derived->failures);
            self::assertSame(['x' => 1], $derived->config);
        }
    }

    public function testDerivedListsKeepNumericKeys(): void
    {
        $list = self::list()->withFilters([
            7 => self::filter('stub', '7', []),
            9 => self::filter('stub', '9'),
        ]);

        self::assertSame([9], \array_keys($list->whole()->filters));
    }

    public function testWithoutExclusiveFiltersTheListIsItsOwnWhole(): void
    {
        $list = self::list()->withFilter(self::filter('stub', 'a'));

        self::assertSame($list, $list->whole());
        self::assertSame($list, $list->forOutput(Output::ListView));
    }

    public function testWithFilterAddsANonExclusiveFilter(): void
    {
        $filter = self::filter('stub', 'f');

        self::assertSame(['f'], \array_keys(self::list()->withFilter($filter)->whole()->filters));
    }
}
