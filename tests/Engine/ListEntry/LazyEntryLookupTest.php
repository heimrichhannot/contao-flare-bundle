<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\ListEntry;

use HeimrichHannot\FlareBundle\Engine\ListEntry\LazyEntryLookup;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use PHPUnit\Framework\TestCase;

final class LazyEntryLookupTest extends TestCase
{
    public function testTheLookupIsBuiltOnTheFirstCallOnlyAndKept(): void
    {
        $builds = 0;
        $inner = new class implements EntryLookupInterface {
            /** @var list<mixed> */
            public array $calls = [];

            public function lookup(mixed $ids): Entry|EntryCollection|null
            {
                $this->calls[] = $ids;

                return null;
            }
        };

        $lazy = new LazyEntryLookup(static function () use (&$builds, $inner): EntryLookupInterface {
            ++$builds;

            return $inner;
        });
        self::assertSame(0, $builds);

        $lazy->lookup(1);
        $lazy->lookup([2, 3]);

        self::assertSame(1, $builds);
        self::assertSame([1, [2, 3]], $inner->calls);
    }
}
