<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List\Entry;

use HeimrichHannot\FlareBundle\Exception\EntryNameCollisionError;
use HeimrichHannot\FlareBundle\Exception\ReadonlyEntryError;
use HeimrichHannot\FlareBundle\Exception\UndefinedEntryKeyError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use PHPUnit\Framework\TestCase;

class EntryTest extends TestCase
{
    private static function entries(array $rows, ?\Closure $linker = null): EntryCollection
    {
        return new EntryCollection($rows, $linker ?? static fn (Entry $e): string => '/reader/' . $e->row['id']);
    }

    public function testToIsTheLinkAndRowToTheColumn(): void
    {
        $entry = self::entries([['id' => 1, 'to' => 'column']])->first();

        self::assertSame('/reader/1', $entry->to);
        self::assertSame('/reader/1', $entry->to());
        self::assertSame('column', $entry->row['to']);
    }

    public function testLinkIsAskedOncePerEntry(): void
    {
        $calls = 0;
        $entry = self::entries([['id' => 1]], static function () use (&$calls): string {
            ++$calls;
            return '/x';
        })->first();
        $entry->to;
        $entry->to();
        self::assertSame(1, $calls);
    }

    public function testToIsNullWithoutALinker(): void
    {
        self::assertNull((new EntryCollection([['id' => 1]]))->first()->to());
        self::assertNull((new Entry(['id' => 1]))->to());
    }

    public function testNullColumnIsSetAndReadable(): void
    {
        $entry = self::entries([['id' => 1, 'subtitle' => null]])->first();
        self::assertTrue(isset($entry->subtitle));
        self::assertNull($entry->subtitle);
    }

    public function testUnknownNameThrows(): void
    {
        $this->expectException(UndefinedEntryKeyError::class);
        self::entries([['id' => 1]])->first()->missing;
    }

    public function testUnknownNameIsNotSet(): void
    {
        self::assertFalse(isset(self::entries([['id' => 1]])->first()->missing));
    }

    public function testLazyValueRunsOnce(): void
    {
        $calls = 0;
        $entry = self::entries([['id' => 1]])->first();
        $entry->attach('isNew', static function () use (&$calls): bool {
            ++$calls;
            return true;
        });
        self::assertTrue($entry->isNew);
        self::assertTrue($entry->isNew);
        self::assertSame(1, $calls);
    }

    public function testLazyNullIsMemoized(): void
    {
        $calls = 0;
        $entry = self::entries([['id' => 1]])->first();
        $entry->attach('nothing', static function () use (&$calls): null {
            ++$calls;
            return null;
        });
        self::assertNull($entry->nothing);
        self::assertNull($entry->nothing);
        self::assertTrue(isset($entry->nothing));
        self::assertSame(1, $calls);
    }

    public function testPlainValueIsReadable(): void
    {
        $entry = self::entries([['id' => 1]])->first();
        $entry->attach('isNew', true);
        self::assertTrue($entry->isNew);
        self::assertTrue($entry->ownsAttachment('isNew'));
    }

    public function testColumnWinsOverCollectionAttachmentOfAnotherRow(): void
    {
        $entries = self::entries([['id' => 1]]);
        $entries->attach('archive', static fn (): \Closure => static fn (Entry $e): string => 'A' . $e->row['id']);
        $entry = $entries->first();
        self::assertTrue(isset($entry->archive));
        self::assertSame('A1', $entry->archive);
    }

    /** @dataProvider provideCollisions */
    public function testEntryAttachCollisionsThrow(string $name): void
    {
        $entries = self::entries([['id' => 1, 'title' => 'T']]);
        $entries->attach('archive', static fn (): \Closure => static fn (): null => null);
        $entry = $entries->first();
        $entry->attach('isNew', true);

        $this->expectException(EntryNameCollisionError::class);
        $entry->attach($name, 'x');
    }

    /** @return iterable<string, array{string}> */
    public static function provideCollisions(): iterable
    {
        yield 'to' => ['to'];
        yield 'row' => ['row'];
        yield 'meta' => ['meta'];
        yield 'column' => ['title'];
        yield 'attached twice' => ['isNew'];
        yield 'collection attachment' => ['archive'];
    }

    public function testEntryIsNotIterable(): void
    {
        self::assertNotInstanceOf(\Traversable::class, self::entries([['id' => 1]])->first());
    }

    public function testWritingOrUnsettingAPropertyThrows(): void
    {
        $entry = self::entries([['id' => 1]])->first();
        try {
            $entry->archive = 'x';
            self::fail();
        } catch (ReadonlyEntryError) {
        }
        $this->expectException(ReadonlyEntryError::class);
        unset($entry->id);
    }
}
