<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Specification;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use PHPUnit\Framework\TestCase;

final class ListSpecificationTest extends TestCase
{
    public function testAddFilterKeysByAliasByDefault(): void
    {
        $spec = new ListSpecification('test', 'tl_test');
        $filter = new Filter(element: 'flare_bool', alias: 'color');

        $spec->addFilter($filter);

        self::assertSame($filter, $spec->getFilter('color'));
        self::assertSame(['color'], \array_keys($spec->getFilters()));
    }

    public function testAddFilterWithExplicitKeyAndGeneratedKeys(): void
    {
        $spec = new ListSpecification('test', 'tl_test');

        $spec->addFilter(new Filter(element: 'a'), 'custom');
        $spec->addFilter(new Filter(element: 'b'));
        $spec->addFilter(new Filter(element: 'c'));

        $keys = \array_keys($spec->getFilters());

        self::assertSame('custom', $keys[0]);
        self::assertCount(3, $keys);
        self::assertSame(\count($keys), \count(\array_unique($keys)));
    }

    public function testHasFilterOfType(): void
    {
        $spec = new ListSpecification('test', 'tl_test');
        $spec->addFilter(new Filter(element: 'flare_published'));

        self::assertTrue($spec->hasFilterOfType('flare_published'));
        self::assertFalse($spec->hasFilterOfType('flare_bool'));
    }

    public function testHashReflectsFilterChanges(): void
    {
        $spec = new ListSpecification('test', 'tl_test');
        $before = $spec->hash();

        $spec->addFilter(new Filter(element: 'flare_bool', config: ['field' => 'published']), 'x');
        $after = $spec->hash();

        self::assertNotSame($before, $after);
        self::assertSame($after, $spec->hash());
    }

    public function testRemoveFilter(): void
    {
        $spec = new ListSpecification('test', 'tl_test');
        $spec->addFilter(new Filter(element: 'a'), 'x');
        $spec->removeFilter('x');

        self::assertNull($spec->getFilter('x'));
        self::assertSame([], $spec->getFilters());
    }
}
