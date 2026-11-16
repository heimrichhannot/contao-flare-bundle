<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Integration\ContaoCalendar\EventListener;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Events;
use Contao\Model;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\EventListener\EventsSchemaOrgListener;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Tests\Fixture\TestModelFixture;

class EventsSchemaOrgListenerTest extends ContaoTestCase
{
    public function testAnEntryOfAnotherTableIsLeftAlone(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects(self::never())->method('getAdapter');

        $event = $this->event(self::entry('tl_test', ['id' => 7]));
        (new EventsSchemaOrgListener($framework))($event);

        self::assertSame(['kept' => true], $event->data);
    }

    public function testAnEntryWithoutModelRefIsLeftAlone(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects(self::never())->method('getAdapter');

        $event = $this->event(new Entry(['id' => 7]));
        (new EventsSchemaOrgListener($framework))($event);

        self::assertSame(['kept' => true], $event->data);
    }

    public function testTheRecordIsLoadedThroughTheFinderOfTheRegisteredClass(): void
    {
        // A subclass of CalendarEventsModel, as a multilingual class would be: the finder of the *named* class is used.
        $model = $this->createMock(CalendarEventsModel::class);
        $class = $model::class;

        $finder = $this->mockAdapter(['findById']);
        $finder->expects(self::once())->method('findById')->with(self::identicalTo(7))->willReturn($model);

        $events = $this->mockAdapter(['getSchemaOrgData']);
        $events
            ->expects(self::once())
            ->method('getSchemaOrgData')
            ->with(self::identicalTo($model))
            ->willReturn(['@type' => 'Event']);

        $event = $this->event(self::entry('tl_calendar_events', ['id' => 7]));
        $this->listener($class, $finder, $events)($event);

        self::assertSame(['@type' => 'Event'], $event->data);
    }

    public function testAStringIdIsPassedUnchanged(): void
    {
        $model = $this->createMock(CalendarEventsModel::class);

        $finder = $this->mockAdapter(['findById']);
        $finder->expects(self::once())->method('findById')->with(self::identicalTo('7'))->willReturn($model);

        $events = $this->mockConfiguredAdapter(['getSchemaOrgData' => ['@type' => 'Event']]);

        $event = $this->event(self::entry('tl_calendar_events', ['id' => '7']));
        $this->listener($model::class, $finder, $events)($event);

        self::assertSame(['@type' => 'Event'], $event->data);
    }

    public function testAMissingRecordYieldsNoData(): void
    {
        $finder = $this->mockAdapter(['findById']);
        $finder->expects(self::once())->method('findById')->willReturn(null);

        $events = $this->mockAdapter(['getSchemaOrgData']);
        $events->expects(self::never())->method('getSchemaOrgData');

        $event = $this->event(self::entry('tl_calendar_events', ['id' => 7]));
        $this->listener(CalendarEventsModel::class, $finder, $events)($event);

        self::assertSame(['kept' => true], $event->data);
    }

    public function testAModelOfAnotherClassYieldsNoData(): void
    {
        $finder = $this->mockAdapter(['findById']);
        $finder->expects(self::once())->method('findById')->willReturn($this->createMock(TestModelFixture::class));

        $events = $this->mockAdapter(['getSchemaOrgData']);
        $events->expects(self::never())->method('getSchemaOrgData');

        $event = $this->event(self::entry('tl_calendar_events', ['id' => 7]));
        $this->listener(TestModelFixture::class, $finder, $events)($event);

        self::assertSame(['kept' => true], $event->data);
    }

    public function testABrokenModelRegistrationFailsTheRequest(): void
    {
        $model = $this->mockAdapter(['getClassFromTable']);
        $model->method('getClassFromTable')->willThrowException(new \RuntimeException('no model'));

        $listener = new EventsSchemaOrgListener($this->mockContaoFramework([Model::class => $model]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no model');

        $listener($this->event(self::entry('tl_calendar_events', ['id' => 7])));
    }

    private function listener(string $class, Adapter $finder, Adapter $events): EventsSchemaOrgListener
    {
        $model = $this->mockAdapter(['getClassFromTable']);
        $model->expects(self::once())->method('getClassFromTable')->with('tl_calendar_events')->willReturn($class);

        // No adapter for CalendarEventsModel::class unless it is the registered class: the table guard needs none.
        return new EventsSchemaOrgListener($this->mockContaoFramework([
            Model::class => $model,
            $class => $finder,
            Events::class => $events,
        ]));
    }

    private function event(Entry $entry): ReaderSchemaOrgEvent
    {
        $list = new ListCompound(
            driver: $this->createStub(ListDriverInterface::class),
            type: 'test_list',
            dc: 'tl_calendar_events',
        );

        return new ReaderSchemaOrgEvent($list, $entry, ['kept' => true]);
    }

    /** @param array<string, mixed> $row */
    private static function entry(string $table, array $row): Entry
    {
        $entry = EntryCollection::forTable($table, [$row])->first();
        self::assertNotNull($entry);

        return $entry;
    }
}
