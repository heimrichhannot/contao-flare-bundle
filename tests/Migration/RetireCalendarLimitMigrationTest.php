<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Migration;

use HeimrichHannot\FlareBundle\Migration\BackfillFilterValuesMigration;
use HeimrichHannot\FlareBundle\Migration\RetireCalendarLimitMigration;
use PHPUnit\Framework\TestCase;

final class RetireCalendarLimitMigrationTest extends TestCase
{
    use FakeFilterTableTrait;

    /** The backfill's columns, the calendar element's period columns and the exclusive setting. */
    private const COLUMNS = [
        'id', 'type', 'intrinsic', 'boolMode', 'preselect', 'prefill', 'control', 'value', 'ctl_boolBinaryChoices',
        'ctl_isMandatory', 'ctl_isMultiple', 'ctl_isExpanded', 'ctl_hasEmptyOption', 'ctl_formatEmptyOption',
        'ctl_formatEmptyOptionCustom', 'ctl_placeholder', 'elm_whitelistParents', 'elm_groupWhitelistParents',
        'title', 'ctl_label', 'elm_isLimited', 'elm_configureStart', 'elm_configureStop', 'elm_startAt', 'elm_stopAt',
        'exclusive', 'outputs',
    ];

    private const MARKER = 'calendarLimitRetired';

    private const CALENDAR = 'flare_calendar_current';

    /** What an unlimited row gets: exclusive to the list view. */
    private const LIST_VIEW_ONLY = ['exclusive' => '1', 'outputs' => 'a:1:{i:0;s:9:"list_view";}'];

    /** Whether the injected backfill reports pending rows. */
    private bool $backfillPending = false;

    protected function setUp(): void
    {
        $this->setUpFakeTable(self::COLUMNS);
        $this->backfillPending = false;
    }

    public function testAReadableLeftoverIsSwitchedOn(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday']);

        $this->migration()->run();

        self::assertSame(
            [[['elm_configureStart' => 'str'] + self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 1]]],
            $this->rowUpdates(),
        );
        self::assertSame('str', $this->rows[1]['elm_configureStart']);
        self::assertSame('', $this->rows[1]['elm_configureStop'], 'the stop side is untouched');
        self::assertSame('', $this->rows[1]['elm_stopAt']);
    }

    public function testATimeWindowRowIsRetiredToo(): void
    {
        $this->addRow(1, 'flare_time_window', ['elm_startAt' => 'last monday']);

        $this->migration()->run();

        self::assertSame(
            [[['elm_configureStart' => 'str'] + self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 1]]],
            $this->rowUpdates(),
        );
        self::assertSame('str', $this->rows[1]['elm_configureStart']);
    }

    public function testAReadableLeftoverIsSwitchedOnWhateverTheLimit(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_isLimited' => '1', 'elm_stopAt' => '+1 week']);
        $this->addRow(2, self::CALENDAR, ['elm_isLimited' => '', 'elm_stopAt' => '+1 week']);

        $this->migration()->run();

        self::assertSame([
            [['elm_configureStop' => 'str', self::MARKER => '1'], ['id' => 1]],
            [['elm_configureStop' => 'str'] + self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 2]],
        ], $this->rowUpdates());
    }

    public function testATimeSpanKeyLeftoverStaysOff(): void
    {
        // v0.1 read the text with plain `strtotime()`, which cannot read a time-span key: the side is open today.
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'this_week']);

        $this->migration()->run();

        self::assertSame([[self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 1]]], $this->rowUpdates());
        self::assertSame('', $this->rows[1]['elm_configureStart']);
    }

    public function testAnUnreadableLeftoverStaysOff(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'next blursday', 'elm_stopAt' => 'next blursday']);

        $this->migration()->run();

        self::assertSame([[self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 1]]], $this->rowUpdates());
    }

    public function testANumericLeftoverStaysOff(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => '1767225600']);
        $this->addRow(2, self::CALENDAR, ['elm_startAt' => '20260101', 'elm_stopAt' => ' 20260101 ']);
        $this->addRow(3, self::CALENDAR, ['elm_stopAt' => '0900']);

        $this->migration()->run();

        self::assertSame([
            [self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 1]],
            [self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 2]],
            [self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 3]],
        ], $this->rowUpdates());
    }

    public function testAConfiguredSideIsLeftAlone(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_configureStart' => 'today', 'elm_startAt' => '']);
        $this->addRow(2, self::CALENDAR, ['elm_configureStart' => 'str', 'elm_startAt' => 'next blursday']);

        $this->migration()->run();

        self::assertSame([
            [self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 1]],
            [self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 2]],
        ], $this->rowUpdates());
        self::assertSame('today', $this->rows[1]['elm_configureStart']);
        self::assertSame('str', $this->rows[2]['elm_configureStart']);
    }

    public function testNothingElseIsWritten(): void
    {
        $value = \serialize(['today', null]);
        $this->addRow(1, self::CALENDAR, [
            'elm_isLimited' => '1',
            'control' => 'flare_date_range',
            'value' => $value,
            'elm_startAt' => 'last monday',
        ]);

        $this->migration()->run();

        $updates = $this->rowUpdates();

        self::assertCount(1, $updates);
        self::assertSame(['elm_configureStart', self::MARKER], \array_keys($updates[0][0]));
        self::assertSame('flare_date_range', $this->rows[1]['control']);
        self::assertSame($value, $this->rows[1]['value']);
    }

    public function testAnUnlimitedRowBecomesExclusiveToTheListView(): void
    {
        $value = \serialize(['today', null]);
        $this->addRow(1, self::CALENDAR, [
            'elm_isLimited' => '',
            'control' => 'flare_date_range',
            'value' => $value,
        ]);

        $this->migration()->run();

        self::assertSame([[self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 1]]], $this->rowUpdates());
        self::assertSame('1', $this->rows[1]['exclusive']);
        self::assertSame(\serialize(['list_view']), $this->rows[1]['outputs']);
        self::assertSame('flare_date_range', $this->rows[1]['control']);
        self::assertSame($value, $this->rows[1]['value']);
    }

    public function testALimitedRowStaysNonExclusive(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_isLimited' => '1', 'control' => 'flare_date_range']);

        $this->migration()->run();

        self::assertSame([[[self::MARKER => '1'], ['id' => 1]]], $this->rowUpdates());
        self::assertSame('0', $this->rows[1]['exclusive']);
        self::assertNull($this->rows[1]['outputs']);
    }

    public function testTheResultCountsTheRowsToCheck(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_isLimited' => '', 'control' => 'flare_date_range']);
        $this->addRow(2, self::CALENDAR, ['elm_isLimited' => '', 'value' => \serialize(['today', null])]);
        $this->addRow(3, self::CALENDAR, ['elm_isLimited' => '1', 'control' => 'flare_date_range']);
        $this->addRow(4, self::CALENDAR, ['elm_isLimited' => '']);
        $this->addRow(5, self::CALENDAR, ['elm_isLimited' => '', 'value' => \serialize([null, ''])]);

        $migration = $this->migration();
        $result = $migration->run();

        self::assertTrue($result->isSuccessful());
        self::assertSame(
            $migration->getName() . ' (4 filters updated, 2 calendar filters with a control or a fixed value now stay'
                . ' within their period: check them)',
            $result->getMessage(),
        );
    }

    public function testAnIntegerSideIsSetAndABlankOneIsNot(): void
    {
        $this->addRow(1, self::CALENDAR, ['value' => \serialize([null, 1767225600])]);
        $this->addRow(2, self::CALENDAR, ['value' => \serialize(['  ', null])]);

        $migration = $this->migration();

        self::assertSame(
            $migration->getName() . ' (2 filters updated, 1 calendar filters with a control or a fixed value now stay'
                . ' within their period: check them)',
            $migration->run()->getMessage(),
        );
    }

    public function testShouldRunIsFalseWithoutElmIsLimited(): void
    {
        $this->columns = \array_values(\array_diff(self::COLUMNS, ['elm_isLimited']));
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday']);

        self::assertFalse($this->migration()->shouldRun());
    }

    public function testShouldRunWaitsForTheExclusiveSetting(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday']);

        foreach (['exclusive', 'outputs'] as $target)
        {
            $this->columns = \array_values(\array_diff(self::COLUMNS, [$target]));

            self::assertFalse($this->migration()->shouldRun(), $target);
        }

        $this->columns = self::COLUMNS;

        self::assertTrue($this->migration()->shouldRun());
    }

    public function testShouldRunWaitsForTheBackfill(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday']);
        $this->backfillPending = true;

        self::assertFalse($this->migration()->shouldRun());

        $this->backfillPending = false;

        self::assertTrue($this->migration()->shouldRun());
    }

    public function testShouldRunWaitsForTheRenamedColumns(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday']);

        $this->columns = \array_map(
            static fn (string $column): string => $column === 'elm_configureStart' ? 'configureStart' : $column,
            self::COLUMNS,
        );

        self::assertFalse($this->migration()->shouldRun());

        $this->columns = self::COLUMNS;

        self::assertTrue($this->migration()->shouldRun());
    }

    public function testShouldRunWaitsForControlAndValue(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday']);

        foreach (['control', 'value'] as $target)
        {
            $this->columns = \array_values(\array_diff(self::COLUMNS, [$target]));

            self::assertFalse($this->migration()->shouldRun(), $target);
        }
    }

    public function testShouldRunIsFalseWithoutTheTable(): void
    {
        $this->columns = null;

        self::assertFalse($this->migration()->shouldRun());
    }

    public function testShouldRunMatchesColumnNamesCaseInsensitively(): void
    {
        $this->columns = \array_map('strtolower', self::COLUMNS);

        self::assertTrue($this->migration()->shouldRun());
    }

    public function testRunAddsTheMarkerAndDefaultsItToDone(): void
    {
        $this->addRow(1, self::CALENDAR, []);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([
            "ALTER TABLE tl_flare_filter ADD calendarLimitRetired CHAR(1) NOT NULL DEFAULT '0'",
            "ALTER TABLE tl_flare_filter ALTER calendarLimitRetired SET DEFAULT '1'",
        ], $this->statements);
        self::assertContains(self::MARKER, $this->columns);
        self::assertSame('1', $this->markerDefaults[self::MARKER]);

        // A second run does not add the column again; switching the default is idempotent.
        $this->statements = [];
        $this->migration()->run();

        self::assertSame(["ALTER TABLE tl_flare_filter ALTER calendarLimitRetired SET DEFAULT '1'"], $this->statements);
    }

    public function testOtherTypesAreMarkedDone(): void
    {
        $this->addRow(1, 'flare_published', ['elm_startAt' => 'last monday']);
        $this->addRow(2, 'flare_dateRange', ['elm_startAt' => 'last monday']);

        $this->migration()->run();

        self::assertSame([], $this->rowUpdates());
        self::assertSame([[[self::MARKER => '1'], [self::MARKER => '0']]], $this->updates);
        self::assertSame('1', $this->rows[1][self::MARKER]);
        self::assertSame('1', $this->rows[2][self::MARKER]);
        self::assertSame('', $this->rows[2]['elm_configureStart']);
        self::assertFalse($this->migration()->shouldRun());
    }

    public function testRunResumesAnInterruptionBeforeOtherTypesAreMarkedDone(): void
    {
        // Interrupted after the per-row loop: the calendar row is done, the row of another type is still pending.
        $this->columns[] = self::MARKER;
        $this->markerDefaults[self::MARKER] = '1';
        $this->addRow(1, self::CALENDAR, [
            self::MARKER => '1',
            'elm_configureStart' => 'str',
            'elm_startAt' => 'today',
        ]);
        $this->addRow(2, 'flare_published', [self::MARKER => '0', 'elm_startAt' => 'last monday']);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([], $this->rowUpdates());
        self::assertSame('1', $this->rows[2][self::MARKER]);
        self::assertSame('', $this->rows[2]['elm_configureStart']);
        self::assertFalse($this->migration()->shouldRun());
    }

    public function testShouldRunGoesQuietAfterARun(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday']);
        $this->addRow(2, self::CALENDAR, ['elm_startAt' => 'next blursday', 'control' => 'flare_date_range']);
        $this->addRow(3, 'flare_published', []);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertFalse($this->migration()->shouldRun());

        foreach ($this->rows as $id => $row) {
            self::assertSame('1', $row[self::MARKER], (string) $id);
        }
    }

    public function testTheDeleteRunLeavesItQuiet(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday']);
        $this->migration()->run();

        // An editor switches the side off again; the save callback keeps the text.
        $this->rows[1]['elm_configureStart'] = '';

        // The schema diff's deletes drop the marker and `elm_isLimited`, then `contao:migrate` runs the migrations.
        $this->columns = \array_values(\array_diff($this->columns ?? [], [self::MARKER, 'elm_isLimited']));
        unset($this->rows[1][self::MARKER], $this->rows[1]['elm_isLimited']);

        self::assertFalse($this->migration()->shouldRun());
    }

    public function testRowsCreatedAfterwardsAreNeverPending(): void
    {
        $this->addRow(1, self::CALENDAR, []);
        $this->migration()->run();

        // An editor's new filter with a side switched off over a readable text.
        $this->addRow(2, self::CALENDAR, ['elm_startAt' => 'last monday']);

        self::assertSame('1', $this->rows[2][self::MARKER]);
        self::assertFalse($this->migration()->shouldRun());

        $this->updates = [];
        $this->migration()->run();

        self::assertSame([], $this->rowUpdates());
        self::assertSame('', $this->rows[2]['elm_configureStart']);
    }

    public function testEveryRowIsUpdatedInOneStatementWithItsMarker(): void
    {
        $this->addRow(1, self::CALENDAR, ['elm_startAt' => 'last monday', 'elm_stopAt' => 'next friday']);
        $this->addRow(2, self::CALENDAR, []);
        $this->addRow(3, 'flare_published', []);

        $this->migration()->run();

        self::assertSame([
            [
                ['elm_configureStart' => 'str', 'elm_configureStop' => 'str']
                    + self::LIST_VIEW_ONLY
                    + [self::MARKER => '1'],
                ['id' => 1],
            ],
            [self::LIST_VIEW_ONLY + [self::MARKER => '1'], ['id' => 2]],
            [[self::MARKER => '1'], [self::MARKER => '0']],
        ], $this->updates);
    }

    /**
     * The update from v0.1 in one `contao:migrate`: both migrations run on one table, the backfill first.
     */
    public function testAV01RowIsBackfilledFirstAndThenChecked(): void
    {
        $this->addRow(1, self::CALENDAR, [
            'intrinsic' => '0',
            'control' => '',
            'elm_isLimited' => '0',
            'elm_configureStart' => '',
            'elm_startAt' => 'today',
        ]);

        $connection = $this->fakeConnection();
        $backfill = new BackfillFilterValuesMigration($connection, $this->fakeFramework());
        $migration = new RetireCalendarLimitMigration($connection, $backfill);

        self::assertTrue($backfill->shouldRun());
        self::assertFalse($migration->shouldRun());

        $backfill->run();
        $backfilled = $this->rows[1];

        self::assertSame('flare_date_range', $backfilled['control']);
        self::assertFalse($backfill->shouldRun());
        self::assertTrue($migration->shouldRun());

        $result = $migration->run();

        self::assertSame('str', $this->rows[1]['elm_configureStart']);
        self::assertSame('1', $this->rows[1]['exclusive']);
        self::assertSame(\serialize(['list_view']), $this->rows[1]['outputs']);
        self::assertSame($backfilled['control'], $this->rows[1]['control']);
        self::assertSame($backfilled['value'], $this->rows[1]['value']);
        self::assertSame(
            $migration->getName() . ' (1 filters updated, 1 calendar filters with a control or a fixed value now stay'
                . ' within their period: check them)',
            $result->getMessage(),
        );
        self::assertFalse($migration->shouldRun());
    }

    /**
     * A migration over the fake table, with a backfill that reports `$this->backfillPending`.
     */
    private function migration(): RetireCalendarLimitMigration
    {
        $backfill = $this->createMock(BackfillFilterValuesMigration::class);
        $backfill->method('shouldRun')->willReturnCallback(fn (): bool => $this->backfillPending);
        $backfill->expects(self::never())->method('run');

        return new RetireCalendarLimitMigration($this->fakeConnection(), $backfill);
    }
}
