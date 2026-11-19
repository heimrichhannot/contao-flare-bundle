<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use HeimrichHannot\FlareBundle\Migration\BackfillFilterValuesMigration;
use HeimrichHannot\FlareBundle\Migration\CalendarColumnsMigration;
use HeimrichHannot\FlareBundle\Migration\RenameTimeWindowTypeMigration;
use HeimrichHannot\FlareBundle\Migration\RetireCalendarLimitMigration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RenameTimeWindowTypeMigrationTest extends TestCase
{
    use FakeFilterTableTrait;

    private const OLD = 'flare_calendar_current';

    private const NEW = 'flare_time_window';

    private const UPDATE = 'UPDATE tl_flare_filter SET type = ? WHERE CAST(type AS BINARY) = ?';

    /** The element's picker and switch columns, which the schema update adds. */
    private const PICKER_COLUMNS = [
        'elm_periodStartField', 'elm_periodStopField', 'elm_recurringField', 'elm_recurrencesField',
        'elm_repeatEndField', 'elm_overlap', 'elm_useRecurrence',
    ];

    /**
     * A v0.1 table after the schema update: the columns of `RetireCalendarLimitMigrationTest::COLUMNS`, the pickers and
     * the source of `CalendarColumnsMigration`.
     */
    private const V01_COLUMNS = [
        'id', 'type', 'intrinsic', 'boolMode', 'preselect', 'prefill', 'control', 'value', 'ctl_boolBinaryChoices',
        'ctl_isMandatory', 'ctl_isMultiple', 'ctl_isExpanded', 'ctl_hasEmptyOption', 'ctl_formatEmptyOption',
        'ctl_formatEmptyOptionCustom', 'ctl_placeholder', 'elm_whitelistParents', 'elm_groupWhitelistParents',
        'title', 'ctl_label', 'elm_isLimited', 'elm_configureStart', 'elm_configureStop', 'elm_startAt', 'elm_stopAt',
        'exclusive', 'outputs',
        ...self::PICKER_COLUMNS,
        'elm_hasExtendedEvents',
    ];

    /** The marker columns of the earlier migrations. */
    private const MARKERS = ['legacyBackfilled', 'calendarLimitRetired', 'calendarColumnsFilled'];

    protected function setUp(): void
    {
        $this->setUpFakeTable(['id', 'type']);
    }

    public function testOldRowsAreRenamed(): void
    {
        $this->addRow(1, self::OLD, []);
        $this->addRow(2, 'flare_published', []);

        $migration = $this->migration();

        self::assertTrue($migration->shouldRun());

        $result = $migration->run();

        self::assertTrue($result->isSuccessful());
        self::assertSame($migration->getName() . ' (1 filters renamed)', $result->getMessage());
        self::assertSame(self::NEW, $this->rows[1]['type']);
        self::assertSame('flare_published', $this->rows[2]['type']);
        self::assertFalse($migration->shouldRun());
    }

    public function testACustomTypeInAnotherCaseIsNotRenamed(): void
    {
        $this->addRow(1, 'Flare_Calendar_Current', []);

        self::assertFalse($this->migration()->shouldRun());
    }

    public function testTheUpdateIsByteExact(): void
    {
        $this->addRow(1, self::OLD, []);
        $this->addRow(2, 'Flare_Calendar_Current', []);

        $migration = $this->migration();

        self::assertSame($migration->getName() . ' (1 filters renamed)', $migration->run()->getMessage());
        self::assertSame([[self::UPDATE, [self::NEW, self::OLD]]], $this->statementCalls);
        self::assertSame(self::NEW, $this->rows[1]['type']);
        self::assertSame('Flare_Calendar_Current', $this->rows[2]['type']);
    }

    public function testOnlyTheTypeChanges(): void
    {
        // An already-migrated row an editor changed since: custom start picker, cleared end picker, recurrence off.
        // The older migrations' source and marker columns are gone.
        $this->setUpFakeTable(['id', 'type', ...self::PICKER_COLUMNS]);
        $this->addRow(1, self::OLD, [
            'elm_periodStartField' => 'startStr',
            'elm_periodStopField' => '',
            'elm_useRecurrence' => 0,
        ]);
        unset($this->rows[1]['elm_hasExtendedEvents']);

        foreach (self::MARKERS as $marker) {
            unset($this->rows[1][$marker]);
        }

        $before = $this->rows[1];

        $this->migration()->run();

        self::assertSame(\array_merge($before, ['type' => self::NEW]), $this->rows[1]);
    }

    public function testQuietWithoutTableOrTypeColumn(): void
    {
        foreach ([null, ['id', 'title']] as $columns)
        {
            $this->columns = $columns;
            $connection = $this->fakeConnection();
            \assert($connection instanceof MockObject);
            $connection->expects(self::never())->method('fetchOne');

            self::assertFalse((new RenameTimeWindowTypeMigration($connection))->shouldRun());
        }
    }

    /**
     * The update from v0.1 in one `contao:migrate`: the rename runs in the first pass, before the earlier migrations
     * find their columns. Their result does not depend on that order.
     */
    public function testTheRenameFirstEndsLikeTheOtherMigrationsAlone(): void
    {
        $this->setUpV01Row();
        $this->runEarlierMigrations();
        $alone = $this->rows[1];

        $this->assertEarlierMigrationsDidTheirWork($alone);
        self::assertSame(self::OLD, $alone['type']);

        $this->setUpV01Row();
        $rename = $this->migration();

        self::assertTrue($rename->shouldRun());

        $rename->run();

        self::assertSame(self::NEW, $this->rows[1]['type']);

        $this->runEarlierMigrations();
        $renamedFirst = $this->rows[1];

        $this->assertEarlierMigrationsDidTheirWork($renamedFirst);
        self::assertSame(\array_merge($alone, ['type' => self::NEW]), $renamedFirst);
    }

    private function migration(): RenameTimeWindowTypeMigration
    {
        return new RenameTimeWindowTypeMigration($this->fakeConnection());
    }

    /**
     * A v0.1 calendar row with the period limit off, a readable start text and extended events on.
     */
    private function setUpV01Row(): void
    {
        $this->setUpFakeTable(self::V01_COLUMNS);
        $this->addRow(1, self::OLD, [
            'intrinsic' => '0',
            'control' => '',
            'elm_isLimited' => '0',
            'elm_configureStart' => '',
            'elm_startAt' => 'today',
            'elm_hasExtendedEvents' => '1',
        ]);
    }

    /**
     * Runs the backfill, the retire and the columns migrations as `contao:migrate` does: pass after pass, while any of
     * them is pending.
     */
    private function runEarlierMigrations(): void
    {
        $connection = $this->fakeConnection();
        $backfill = new BackfillFilterValuesMigration($connection, $this->fakeFramework());

        /** @var list<AbstractMigration> $migrations */
        $migrations = [
            $backfill,
            new RetireCalendarLimitMigration($connection, $backfill),
            new CalendarColumnsMigration($connection),
        ];

        for ($pass = 1; ; ++$pass)
        {
            $pending = \array_filter(
                $migrations,
                static fn (AbstractMigration $migration): bool => $migration->shouldRun(),
            );

            if ($pending === []) {
                break;
            }

            if ($pass > 10) {
                self::fail('The migrations are still pending after 10 passes.');
            }

            foreach ($pending as $migration) {
                $migration->run();
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function assertEarlierMigrationsDidTheirWork(array $row): void
    {
        // The backfill.
        self::assertSame('flare_date_range', $row['control']);

        // The retire migration: the readable start text switched on, exclusive to the list view.
        self::assertSame('str', $row['elm_configureStart']);
        self::assertSame('1', $row['exclusive']);
        self::assertSame(\serialize(['list_view']), $row['outputs']);

        // The columns migration.
        self::assertSame('startTime', $row['elm_periodStartField']);
        self::assertSame('endTime', $row['elm_periodStopField']);
        self::assertSame('recurring', $row['elm_recurringField']);
        self::assertSame('recurrences', $row['elm_recurrencesField']);
        self::assertSame('repeatEnd', $row['elm_repeatEndField']);
        self::assertSame(1, $row['elm_useRecurrence']);
        self::assertSame(1, $row['elm_overlap']);

        foreach (self::MARKERS as $marker) {
            self::assertSame('1', $row[$marker], $marker);
        }
    }
}
