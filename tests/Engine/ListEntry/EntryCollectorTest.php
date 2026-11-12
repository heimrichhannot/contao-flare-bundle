<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\ListEntry;

use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
use HeimrichHannot\FlareBundle\Event\ListDriverEntriesEvent;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\Entry\ModelRef;
use HeimrichHannot\FlareBundle\List\ListCompound;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EntryCollectorTest extends TestCase
{
    public function testNonEmptyRowsDispatchOnceAndAreSealed(): void
    {
        $events = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListDriverEntriesEvent::class,
            static function (ListDriverEntriesEvent $e) use (&$events): void {
                $events[] = $e;
            },
        );
        $list = $this->list();
        $intent = new ValidationIntent();

        $linker = static fn (Entry $e): string => '/r/' . $e->row['id'];
        $entries = (new EntryCollector($dispatcher, $list, $intent, $linker))
            ->collect([['id' => 1], ['id' => 2]], $this->createMock(EntryLookupInterface::class));

        self::assertCount(1, $events);
        self::assertSame([$list, $intent, $entries], [$events[0]->list, $events[0]->intent, $events[0]->entries]);
        self::assertTrue($entries->isSealed());
        self::assertEquals(new ModelRef('tl_test', 1), $entries->first()->meta->modelRef);
        self::assertSame('/r/1', $entries->first()->to);
    }

    public function testEmptyRowsDispatchNothing(): void
    {
        $events = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListDriverEntriesEvent::class,
            static function () use (&$events): void {
                ++$events;
            },
        );

        $entries = (new EntryCollector($dispatcher, $this->list(), new ValidationIntent()))
            ->collect([], $this->createMock(EntryLookupInterface::class));

        self::assertSame(0, $events);
        self::assertCount(0, $entries);
        self::assertTrue($entries->isSealed());
    }

    public function testEventAttachHandsPhaseOneTheLookup(): void
    {
        $lookup = $this->createMock(EntryLookupInterface::class);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ListDriverEntriesEvent::class, static fn (ListDriverEntriesEvent $e) => $e->attach(
            'seen',
            static fn (EntryCollection $all, EntryLookupInterface $l): \Closure => static fn (): bool => $l === $lookup,
        ));

        $entry = (new EntryCollector($dispatcher, $this->list(), new ValidationIntent()))
            ->collect([['id' => 1]], $lookup)
            ->first();

        self::assertTrue($entry->seen);
    }

    private function list(): ListCompound
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        return new ListCompound(driver: $driver, type: 'test_list', dc: 'tl_test');
    }
}
