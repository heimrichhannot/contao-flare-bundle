<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Migration;

use HeimrichHannot\FlareBundle\Migration\CalendarColumnsMigration;
use PHPUnit\Framework\TestCase;

final class CalendarColumnsMigrationTest extends TestCase
{
    use FakeFilterTableTrait;

    /** The columns the migration requires, plus the ones the fake rows always carry. */
    private const COLUMNS = [
        'id', 'type', 'elm_hasExtendedEvents', 'elm_periodStartField', 'elm_periodStopField', 'elm_recurringField',
        'elm_recurrencesField', 'elm_repeatEndField', 'elm_overlap', 'elm_useRecurrence',
    ];

    private const MARKER = 'calendarColumnsFilled';

    private const CALENDAR = 'flare_calendar_current';

    /** What a pending calendar row gets, besides the marker. */
    private const EVENT_COLUMNS = [
        'elm_periodStartField' => 'startTime',
        'elm_periodStopField' => 'endTime',
        'elm_recurringField' => 'recurring',
        'elm_recurrencesField' => 'recurrences',
        'elm_repeatEndField' => 'repeatEnd',
        'elm_useRecurrence' => 1,
    ];

    protected function setUp(): void
    {
        $this->setUpFakeTable(self::COLUMNS);
    }

    public function testACalendarRowGetsTheEventColumns(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_hasExtendedEvents' => '']);

        $this->migration()->run();

        self::assertSame(
            [[self::EVENT_COLUMNS + ['elm_overlap' => 0, self::MARKER => '1'], ['id' => 1]]],
            $this->rowUpdates(),
        );
        self::assertSame('startTime', $this->rows[1]['elm_periodStartField']);
        self::assertSame(1, $this->rows[1]['elm_useRecurrence']);
    }

    public function testExtendedEventsBecomeOverlap(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_hasExtendedEvents' => '1']);

        $this->migration()->run();

        self::assertSame(1, $this->rows[1]['elm_overlap']);
        self::assertSame(1, $this->rowUpdates()[0][0]['elm_overlap']);
    }

    public function testAPresetStartPickerIsOnlyMarked(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_periodStartField' => 'begins', 'elm_hasExtendedEvents' => '1']);

        $this->migration()->run();

        self::assertSame([[[self::MARKER => '1'], ['id' => 1]]], $this->rowUpdates());
        self::assertSame('begins', $this->rows[1]['elm_periodStartField']);
        self::assertSame('', $this->rows[1]['elm_periodStopField']);
        self::assertSame(0, $this->rows[1]['elm_overlap']);
    }

    public function testAMarkedRowBesideAPendingOneIsUntouched(): void
    {
        $this->columns[] = self::MARKER;
        $this->markerDefaults[self::MARKER] = '1';
        $this->addRow(1, self::CALENDAR, [self::MARKER => '1', 'elm_periodStartField' => 'begins']);
        $this->addRow(2, self::CALENDAR, [self::MARKER => '0']);

        $this->migration()->run();

        self::assertSame(
            [[self::EVENT_COLUMNS + ['elm_overlap' => 0, self::MARKER => '1'], ['id' => 2]]],
            $this->rowUpdates(),
        );
        self::assertSame('begins', $this->rows[1]['elm_periodStartField']);
        self::assertSame('', $this->rows[1]['elm_periodStopField']);
    }

    public function testOtherTypesAreMarkedDone(): void
    {
        $this->addRow(1, 'flare_published', []);
        $this->addRow(2, 'flare_dateRange', []);

        $this->migration()->run();

        self::assertSame([], $this->rowUpdates());
        self::assertSame([[[self::MARKER => '1'], [self::MARKER => '0']]], $this->updates);
        self::assertSame('1', $this->rows[1][self::MARKER]);
        self::assertSame('1', $this->rows[2][self::MARKER]);
        self::assertSame('', $this->rows[2]['elm_periodStartField']);
    }

    public function testTheResultCountsTheCalendarFilters(): void
    {
        $this->addRow(1, self::CALENDAR, []);
        $this->addRow(2, self::CALENDAR, ['elm_periodStartField' => 'begins']);
        $this->addRow(3, 'flare_published', []);

        $migration = $this->migration();
        $result = $migration->run();

        self::assertTrue($result->isSuccessful());
        self::assertSame(
            $migration->getName() . ' (1 calendar filters set to the event columns)',
            $result->getMessage(),
        );
    }

    public function testRunAddsTheMarkerAndDefaultsItToDone(): void
    {
        $this->addRow(1, self::CALENDAR, []);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([
            "ALTER TABLE tl_flare_filter ADD calendarColumnsFilled CHAR(1) NOT NULL DEFAULT '0'",
            "ALTER TABLE tl_flare_filter ALTER calendarColumnsFilled SET DEFAULT '1'",
        ], $this->statements);
        self::assertSame('1', $this->markerDefaults[self::MARKER]);
    }

    public function testItWaitsForItsColumns(): void
    {
        $this->addRow(1, self::CALENDAR, []);

        // Before the rename of `RenameFilterColumnsMigration`.
        $this->columns = \array_map(
            static fn (string $column): string => $column === 'elm_hasExtendedEvents' ? 'hasExtendedEvents' : $column,
            self::COLUMNS,
        );

        self::assertFalse($this->migration()->shouldRun());

        // Before the schema update.
        $this->columns = \array_values(\array_diff(self::COLUMNS, ['elm_periodStartField']));

        self::assertFalse($this->migration()->shouldRun());

        $this->columns = self::COLUMNS;

        self::assertTrue($this->migration()->shouldRun());
    }

    public function testShouldRunIsFalseWithoutTheTable(): void
    {
        $this->columns = null;

        self::assertFalse($this->migration()->shouldRun());
    }

    public function testItDoesNotWaitForTheOtherMigrations(): void
    {
        // The backfill's columns and the exclusive setting do not exist here: this migration needs none of them, so
        // neither the pending backfill nor the retire migration's columns hold it back.
        $this->addRow(1, self::CALENDAR, []);

        self::assertTrue($this->migration()->shouldRun());
        self::assertNotContains('exclusive', $this->columns);
        self::assertNotContains('outputs', $this->columns);
    }

    public function testShouldRunGoesQuietAfterARun(): void
    {
        $this->addRow(1, self::CALENDAR, []);
        $this->addRow(2, 'flare_published', []);

        $this->migration()->run();

        self::assertFalse($this->migration()->shouldRun());

        foreach ($this->rows as $id => $row) {
            self::assertSame('1', $row[self::MARKER], (string) $id);
        }
    }

    public function testRowsCreatedAfterwardsAreNeverPending(): void
    {
        $this->addRow(1, self::CALENDAR, []);
        $this->migration()->run();

        $this->addRow(2, self::CALENDAR, []);

        self::assertSame('1', $this->rows[2][self::MARKER]);
        self::assertFalse($this->migration()->shouldRun());
    }

    public function testTheDeleteRunLeavesItQuiet(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_hasExtendedEvents' => '1']);
        $this->migration()->run();

        // The schema diff's deletes drop the marker and the source, then `contao:migrate` runs the migrations again.
        $this->columns = \array_values(\array_diff($this->columns ?? [], [self::MARKER, 'elm_hasExtendedEvents']));
        unset($this->rows[1][self::MARKER], $this->rows[1]['elm_hasExtendedEvents']);

        self::assertFalse($this->migration()->shouldRun());
    }

    private function migration(): CalendarColumnsMigration
    {
        return new CalendarColumnsMigration($this->fakeConnection());
    }
}
