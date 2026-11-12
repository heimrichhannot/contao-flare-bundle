<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List\Entry;

use HeimrichHannot\FlareBundle\Exception\EntryNameCollisionError;
use HeimrichHannot\FlareBundle\Exception\MissingEntryIdError;
use HeimrichHannot\FlareBundle\Exception\SealedEntriesError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\ModelRef;
use PHPUnit\Framework\TestCase;

class EntryCollectionTest extends TestCase
{
    public function testCollectionLoaderRunsOncePerCollectionAndOnlyWhenRead(): void
    {
        $phase1 = 0;
        $picks = 0;
        $entries = new EntryCollection([['id' => 1, 'pid' => 10], ['id' => 2, 'pid' => 20]]);
        $entries->attach('archive', function (EntryCollection $all) use (&$phase1, &$picks): \Closure {
            ++$phase1;
            self::assertSame([10, 20], $all->column('pid'));
            return static function (Entry $e) use (&$picks): string {
                ++$picks;
                return 'A' . $e->row['pid'];
            };
        });
        self::assertSame(0, $phase1);

        [$a, $b] = \iterator_to_array($entries);
        self::assertSame('A10', $a->archive);
        self::assertSame('A10', $a->archive);
        self::assertSame('A20', $b->archive);
        self::assertSame([1, 2], [$phase1, $picks]);
    }

    /** @dataProvider provideCollectionCollisions */
    public function testCollectionAttachCollisionsThrow(string $name): void
    {
        // row 2 alone has `extra`: rows may differ
        $entries = new EntryCollection([['id' => 1], ['id' => 2, 'extra' => 'x']]);
        $entries->first()->attach('own', 1);
        $entries->attach('twice', static fn (): \Closure => static fn (): null => null);

        $this->expectException(EntryNameCollisionError::class);
        $entries->attach($name, static fn (): \Closure => static fn (): null => null);
    }

    /** @return iterable<string, array{string}> */
    public static function provideCollectionCollisions(): iterable
    {
        yield 'to' => ['to'];
        yield 'row' => ['row'];
        yield 'meta' => ['meta'];
        yield 'column of another row' => ['extra'];
        yield 'entry attachment' => ['own'];
        yield 'attached twice' => ['twice'];
    }

    public function testAttachAfterSealThrowsOnBothLevels(): void
    {
        $entries = new EntryCollection([['id' => 1]]);
        $entries->seal();
        self::assertTrue($entries->isSealed());

        try {
            $entries->first()->attach('a', 1);
            self::fail();
        } catch (SealedEntriesError) {
        }
        $this->expectException(SealedEntriesError::class);
        $entries->attach('b', static fn (): \Closure => static fn (): null => null);
    }

    public function testForTableSetsAModelRefPerRow(): void
    {
        $entry = EntryCollection::forTable('tl_news', [['id' => '5']])->first();
        self::assertEquals(new ModelRef('tl_news', '5'), $entry->meta->modelRef);
    }

    public function testNewCollectionHasNoModelRef(): void
    {
        self::assertNull((new EntryCollection([['id' => 1]]))->first()->meta->modelRef);
    }

    /** @dataProvider provideRowsWithoutId */
    public function testForTableRejectsARowWithoutId(array $row): void
    {
        $this->expectException(MissingEntryIdError::class);
        EntryCollection::forTable('tl_news', [$row]);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function provideRowsWithoutId(): iterable
    {
        yield 'no id column' => [['title' => 'x']];
        yield 'null id' => [['id' => null]];
        yield 'empty id' => [['id' => '']];
    }

    public function testColumnSkipsMissingAndEmptyValues(): void
    {
        $entries = new EntryCollection([['pid' => '1'], ['pid' => '2'], ['pid' => '1'], ['pid' => null],
            ['pid' => ''], ['pid' => '0'], ['other' => 'x']]);
        self::assertSame(['1', '2'], $entries->column('pid'));
    }

    public function testFirstOfAnEmptyCollectionIsNull(): void
    {
        self::assertNull((new EntryCollection())->first());
        self::assertCount(0, new EntryCollection());
    }

    public function testIsAListWithFreshIteratorsAndRepeatedIds(): void
    {
        $entries = new EntryCollection([['id' => 7], ['id' => 7], ['id' => 8]]);
        self::assertSame([0, 1, 2], \array_keys(\iterator_to_array($entries)));
        self::assertCount(3, \iterator_to_array($entries));
        self::assertCount(3, $entries);
    }
}
