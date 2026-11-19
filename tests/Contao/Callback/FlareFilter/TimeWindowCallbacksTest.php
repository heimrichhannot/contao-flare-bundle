<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Callback\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter\TimeWindowCallbacks;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterListTableResolver;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\Filter\Element\TimeWindowFilterElement;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\Element\StubsContaoDcaTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TimeWindowCallbacksTest extends TestCase
{
    use StubsContaoDcaTrait;

    private const FILTER_ID = 7;
    private const CALENDAR = TimeWindowFilterElement::TYPE;

    /** The prefill of a table with all five event columns. */
    private const EVENT_PREFILL = [
        'elm_periodStartField' => 'startTime',
        'elm_periodStopField' => 'endTime',
        'elm_recurringField' => 'recurring',
        'elm_recurrencesField' => 'recurrences',
        'elm_repeatEndField' => 'repeatEnd',
        'elm_useRecurrence' => 1,
        'elm_overlap' => 0,
    ];

    /** The prefill of a table without event columns. */
    private const EMPTY_PREFILL = [
        'elm_periodStartField' => '',
        'elm_periodStopField' => '',
        'elm_recurringField' => '',
        'elm_recurrencesField' => '',
        'elm_repeatEndField' => '',
        'elm_useRecurrence' => 0,
        'elm_overlap' => 0,
    ];

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->stubDca('tl_test', ['fields' => [
            'startTime' => ['sql' => 'bigint(20) NULL'],
            'begins' => ['sql' => ['type' => 'integer', 'name' => 'physical']],
            'day' => ['sql' => 'date NULL'],
            'virtual' => ['inputType' => 'text'],
        ]]);

        $this->stubDca('tl_events', ['fields' => [
            'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
            'title' => ['sql' => "varchar(255) NOT NULL default ''"],
            'startTime' => ['sql' => 'bigint(20) NULL'],
            'endTime' => ['sql' => 'bigint(20) NULL'],
            'recurring' => ['sql' => ['type' => 'boolean', 'default' => false]],
            'recurrences' => ['sql' => 'smallint(5) unsigned NOT NULL default 0'],
            'repeatEnd' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        ]]);

        $this->stubDca('tl_plain', ['fields' => [
            'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
            'title' => ['sql' => "varchar(255) NOT NULL default ''"],
        ]]);

        $this->stubDca('tl_partial', ['fields' => [
            'startTime' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
            'recurring' => ['sql' => "char(1) NOT NULL default ''"],
        ]]);

        $this->stubDca('tl_datetime', ['fields' => [
            'startTime' => ['sql' => 'datetime NULL'],
            'endTime' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        ]]);
    }

    public function testOptionsArePhysicalNames(): void
    {
        self::assertSame(
            ['physical' => 'tl_test.begins', 'startTime' => 'tl_test.startTime'],
            $this->callbacks('tl_test')->getColumnOptions($this->dataContainer(self::FILTER_ID)),
        );
    }

    public function testNoTableNoOptions(): void
    {
        self::assertSame([], $this->callbacks('')->getColumnOptions($this->dataContainer(self::FILTER_ID)));
        self::assertSame([], $this->callbacks('tl_test')->getColumnOptions($this->dataContainer(99)));
    }

    public function testEveryPickerGetsTheOptions(): void
    {
        self::assertSame([
            'fields.elm_periodStartField.options',
            'fields.elm_periodStopField.options',
            'fields.elm_recurringField.options',
            'fields.elm_recurrencesField.options',
            'fields.elm_repeatEndField.options',
        ], \array_column($this->callbackTargets('getColumnOptions'), 'target'));
    }

    public function testThePrefillIsRegisteredOnSubmitAndAfterTheTypesOtherSaveCallbacks(): void
    {
        self::assertSame(
            [['target' => 'config.onbeforesubmit', 'priority' => null]],
            $this->callbackTargets('onBeforeSubmit'),
        );
        self::assertSame(
            [['target' => 'fields.type.save', 'priority' => -1000]],
            $this->callbackTargets('onSaveType'),
        );
    }

    public function testAnEventTableIsPrefilledOnSubmit(): void
    {
        $callbacks = $this->callbacks('tl_events');
        $this->connection->expects(self::never())->method('update');

        self::assertSame(
            ['type' => self::CALENDAR] + self::EVENT_PREFILL,
            $callbacks->onBeforeSubmit(['type' => self::CALENDAR], $this->dataContainer(self::FILTER_ID)),
        );
    }

    public function testSubmittedValuesWin(): void
    {
        $callbacks = $this->callbacks('tl_events');

        self::assertSame(
            [
                'type' => self::CALENDAR,
                'elm_periodStopField' => '',
                'elm_overlap' => 1,
                'elm_periodStartField' => 'startTime',
                'elm_recurringField' => 'recurring',
                'elm_recurrencesField' => 'recurrences',
                'elm_repeatEndField' => 'repeatEnd',
                'elm_useRecurrence' => 1,
            ],
            $callbacks->onBeforeSubmit(
                ['type' => self::CALENDAR, 'elm_periodStopField' => '', 'elm_overlap' => 1],
                $this->dataContainer(self::FILTER_ID),
            ),
        );
    }

    public function testNoTypeChangeLeavesTheValues(): void
    {
        $values = ['title' => 'Upcoming', 'elm_periodStopField' => ''];

        self::assertSame(
            $values,
            $this->callbacks('tl_events')->onBeforeSubmit($values, $this->dataContainer(self::FILTER_ID)),
        );

        $values = ['type' => self::CALENDAR, 'elm_periodStopField' => ''];
        $callbacks = $this->callbacks('tl_events', storedType: self::CALENDAR);

        self::assertSame($values, $callbacks->onBeforeSubmit($values, $this->dataContainer(self::FILTER_ID)));

        $callbacks = $this->callbacks('tl_events', storedType: self::CALENDAR, contao5: false);
        $this->connection->expects(self::never())->method('update');

        self::assertSame(self::CALENDAR, $callbacks->onSaveType(self::CALENDAR, $this->dataContainer(self::FILTER_ID)));
    }

    public function testAnotherTypeLeavesTheValues(): void
    {
        $values = ['type' => 'flare_published'];

        self::assertSame(
            $values,
            $this->callbacks('tl_events')->onBeforeSubmit($values, $this->dataContainer(self::FILTER_ID)),
        );

        $callbacks = $this->callbacks('tl_events', contao5: false);
        $this->connection->expects(self::never())->method('update');

        self::assertSame(
            'flare_published',
            $callbacks->onSaveType('flare_published', $this->dataContainer(self::FILTER_ID)),
        );
    }

    public function testOnContao5TheSaveCallbackWritesNothing(): void
    {
        $callbacks = $this->callbacks('tl_events');
        $this->connection->expects(self::never())->method('update');

        self::assertSame(self::CALENDAR, $callbacks->onSaveType(self::CALENDAR, $this->dataContainer(self::FILTER_ID)));
    }

    public function testOnContao413TheSaveCallbackWrites(): void
    {
        $callbacks = $this->callbacks('tl_events', contao5: false);
        $this->connection
            ->expects(self::once())
            ->method('update')
            ->with(FilterContainer::TABLE_NAME, self::EVENT_PREFILL, ['id' => self::FILTER_ID]);

        self::assertSame(self::CALENDAR, $callbacks->onSaveType(self::CALENDAR, $this->dataContainer(self::FILTER_ID)));
    }

    public function testATableWithoutEventColumnsGetsEmptyPickers(): void
    {
        self::assertSame(
            ['type' => self::CALENDAR] + self::EMPTY_PREFILL,
            $this->callbacks('tl_plain')->onBeforeSubmit(
                ['type' => self::CALENDAR],
                $this->dataContainer(self::FILTER_ID),
            ),
        );
    }

    public function testRecurrenceStaysOffWithoutAllItsColumns(): void
    {
        self::assertSame(
            [
                'type' => self::CALENDAR,
                'elm_periodStartField' => 'startTime',
                'elm_periodStopField' => '',
                'elm_recurringField' => 'recurring',
                'elm_recurrencesField' => '',
                'elm_repeatEndField' => '',
                'elm_useRecurrence' => 0,
                'elm_overlap' => 0,
            ],
            $this->callbacks('tl_partial')->onBeforeSubmit(
                ['type' => self::CALENDAR],
                $this->dataContainer(self::FILTER_ID),
            ),
        );
    }

    public function testADateTypedEventColumnIsNotPrefilled(): void
    {
        $values = $this->callbacks('tl_datetime')->onBeforeSubmit(
            ['type' => self::CALENDAR],
            $this->dataContainer(self::FILTER_ID),
        );

        self::assertSame('', $values['elm_periodStartField']);
        self::assertSame('endTime', $values['elm_periodStopField']);
    }

    public function testAListWithoutTableGetsEmptyPickers(): void
    {
        $tables = \array_keys($GLOBALS['TL_DCA']);

        self::assertSame(
            ['type' => self::CALENDAR] + self::EMPTY_PREFILL,
            $this->callbacks('')->onBeforeSubmit(['type' => self::CALENDAR], $this->dataContainer(self::FILTER_ID)),
        );

        $callbacks = $this->callbacks('', contao5: false);
        $this->connection
            ->expects(self::once())
            ->method('update')
            ->with(FilterContainer::TABLE_NAME, self::EMPTY_PREFILL, ['id' => self::FILTER_ID]);

        $callbacks->onSaveType(self::CALENDAR, $this->dataContainer(self::FILTER_ID));

        self::assertSame($tables, \array_keys($GLOBALS['TL_DCA']), 'No DCA is loaded without a table.');
    }

    public function testARowReturningToTheTypeIsPrefilledAfresh(): void
    {
        // The row was a calendar filter before, its pickers still hold another table's columns; the values the
        // request submits do not carry them, as the palette of the stored type has no pickers.
        self::assertSame(
            ['type' => self::CALENDAR] + self::EVENT_PREFILL,
            $this->callbacks('tl_events')->onBeforeSubmit(
                ['type' => self::CALENDAR],
                $this->dataContainer(self::FILTER_ID),
            ),
        );

        $callbacks = $this->callbacks('tl_events', contao5: false);
        $this->connection
            ->expects(self::once())
            ->method('update')
            ->with(FilterContainer::TABLE_NAME, self::EVENT_PREFILL, ['id' => self::FILTER_ID]);

        $callbacks->onSaveType(self::CALENDAR, $this->dataContainer(self::FILTER_ID));
    }

    /**
     * @param string       $table      The table the list driver resolves for the filter {@see self::FILTER_ID}.
     * @param string|false $storedType The type the database holds for the filter, false for no row.
     */
    private function callbacks(
        string $table,
        string|false $storedType = 'flare_published',
        bool $contao5 = true,
    ): TimeWindowCallbacks {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('fetchAssociative')->willReturnCallback(
            static fn (string $sql, array $params): array|false =>
                $params[0] === self::FILTER_ID ? ['type' => 'stored', 'dc' => $table] : false,
        );
        $this->connection->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $params) use ($storedType): mixed {
                self::assertSame('SELECT type FROM tl_flare_filter WHERE id = ?', $sql);
                self::assertSame([self::FILTER_ID], $params);

                return $storedType;
            },
        );

        $drivers = (new ListDriverRegistry())->add(
            new class implements ListDriverInterface {
                public function resolveDcTable(string $type, array $config, array $attributes): string
                {
                    return (string) ($config['dc'] ?? '');
                }
            },
            new AsListDriver(type: 'stored'),
            'stored',
        );

        return new TimeWindowCallbacks(
            $this->connection,
            new FilterListTableResolver($this->connection, $drivers),
            $contao5,
        );
    }

    private function dataContainer(int $id): DataContainer
    {
        $dc = $this->createMock(DataContainer::class);
        $dc->method('__get')->willReturnCallback(static fn (string $key): mixed => $key === 'id' ? $id : null);

        return $dc;
    }

    /**
     * @return list<array{target: string, priority: int|null}>
     */
    private function callbackTargets(string $method): array
    {
        $targets = [];

        $reflection = new \ReflectionMethod(TimeWindowCallbacks::class, $method);

        foreach ($reflection->getAttributes(AsCallback::class) as $attribute)
        {
            $callback = $attribute->newInstance();

            self::assertSame(FilterContainer::TABLE_NAME, $callback->table);
            $targets[] = ['target' => $callback->target, 'priority' => $callback->priority];
        }

        return $targets;
    }
}
