<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\ListDriverReference;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use PHPUnit\Framework\TestCase;

final class ListSpecTest extends TestCase
{
    private static function reference(): ListDriverReference
    {
        static $driver = null;
        $driver ??= new class implements ListDriverInterface {};

        return ListDriverReference::registered('test', $driver);
    }

    public function testWithFilterKeysByAliasByDefault(): void
    {
        $spec = new ListSpec(reference: self::reference(), dc: 'tl_test');

        $spec = $spec->withFilter(new Filter(type: 'flare_bool', alias: 'foo'));

        self::assertArrayHasKey('foo', $spec->filters);
    }

    public function testWithFilterAcceptsExplicitKey(): void
    {
        $spec = (new ListSpec(reference: self::reference(), dc: 'tl_test'))
            ->withFilter(new Filter(type: 'flare_bool', alias: 'foo'), 'custom');

        self::assertArrayHasKey('custom', $spec->filters);
        self::assertArrayNotHasKey('foo', $spec->filters);
    }

    public function testWithFilterGeneratesCollisionFreeKeysForAliasLessFilters(): void
    {
        $spec = (new ListSpec(reference: self::reference(), dc: 'tl_test'))
            ->withFilter(new Filter(type: 'a'))
            ->withFilter(new Filter(type: 'b'));

        self::assertArrayHasKey('_generated_0', $spec->filters);
        self::assertArrayHasKey('_generated_1', $spec->filters);

        $spec = $spec->withoutFilter('_generated_0')->withFilter(new Filter(type: 'c'));

        self::assertSame('c', $spec->filters['_generated_0']->type);
        self::assertSame('b', $spec->filters['_generated_1']->type);
    }

    public function testModifiersAreImmutable(): void
    {
        $original = new ListSpec(reference: self::reference(), dc: 'tl_test', config: ['id' => 1]);

        $modified = $original
            ->withFilter(new Filter(type: 'a', alias: 'x'))
            ->withConfig(['id' => 2]);

        self::assertSame([], $original->filters);
        self::assertSame(['id' => 1], $original->config);
        self::assertNotSame($original, $modified);
        self::assertSame(['id' => 2], $modified->config);
        self::assertArrayHasKey('x', $modified->filters);
    }

    public function testHasFilterOfType(): void
    {
        $spec = (new ListSpec(reference: self::reference(), dc: 'tl_test'))
            ->withFilter(new Filter(type: 'flare_published', alias: 'p'));

        self::assertTrue($spec->hasFilterOfType('flare_published'));
        self::assertFalse($spec->hasFilterOfType('flare_bool'));
    }

    public function testHashIsStableAndChangesWithContent(): void
    {
        $make = static fn (array $config = [], ?string $source = null): ListSpec =>
            new ListSpec(reference: self::reference(), dc: 'tl_test', config: $config, source: $source);

        self::assertSame($make()->hash(), $make()->hash());
        self::assertNotSame($make()->hash(), $make(config: ['id' => 1])->hash());
        self::assertNotSame($make()->hash(), $make(source: 'tl_flare_list.5')->hash());
        self::assertNotSame(
            $make()->hash(),
            $make()->withFilter(new Filter(type: 'a', alias: 'x'))->hash(),
        );
    }
}
