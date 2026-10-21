<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use HeimrichHannot\FlareBundle\Migration\BackfillFilterValuesMigration;
use PHPUnit\Framework\TestCase;

final class BackfillFilterValuesMigrationTest extends TestCase
{
    private const TABLE = 'tl_flare_filter';

    private const COLUMNS = [
        'id', 'type', 'intrinsic', 'boolMode', 'preselect', 'prefill', 'control', 'value', 'ctl_boolBinaryChoices',
        'ctl_isMandatory', 'ctl_isMultiple', 'ctl_isExpanded', 'ctl_hasEmptyOption', 'ctl_formatEmptyOption',
        'ctl_formatEmptyOptionCustom', 'ctl_placeholder',
    ];

    /** @var array<int, array<string, mixed>> The table's rows by id; `update()` calls are applied to them. */
    private array $rows = [];

    /** @var list<array{array<string, mixed>, array<string, mixed>}> The recorded `update()` calls: data, criteria. */
    private array $updates = [];

    protected function setUp(): void
    {
        $this->rows = [];
        $this->updates = [];
    }

    public function testIntrinsicArchiveIgnoresLeftoverPreselect(): void
    {
        $this->addRow(1, 'flare_archive', [
            'intrinsic' => '1',
            'preselect' => \serialize(['tl_news_archive.5']),
        ]);

        self::assertFalse($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([], $this->updates);
        self::assertSame('', $this->rows[1]['control']);
        self::assertNull($this->rows[1]['value']);
    }

    public function testControlledArchiveKeepsPreselectAsInitialValue(): void
    {
        $this->addRow(1, 'flare_archive', [
            'intrinsic' => '0',
            'preselect' => \serialize(['tl_news_archive.5']),
            'ctl_placeholder' => 'Pick one',
        ]);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([[
            ['control' => 'flare_choice', 'value' => \serialize(['tl_news_archive.5'])],
            ['id' => 1],
        ]], $this->updates);

        // Archive keeps its empty option and placeholder settings 1:1.
        self::assertSame('Pick one', $this->rows[1]['ctl_placeholder']);
        self::assertSame('0', $this->rows[1]['ctl_hasEmptyOption']);
    }

    public function testKeywordsPrefillOnlyWhenIntrinsic(): void
    {
        $this->addRow(1, 'flare_search_keywords', ['intrinsic' => '1', 'prefill' => 'solar panels']);
        $this->addRow(2, 'flare_search_keywords', ['intrinsic' => '0', 'prefill' => 'leftover']);

        $this->migration()->run();

        self::assertSame('', $this->rows[1]['control']);
        self::assertSame('solar panels', $this->rows[1]['value']);
        self::assertSame('flare_keywords', $this->rows[2]['control']);
        self::assertNull($this->rows[2]['value']);
    }

    public function testTernaryBoolBecomesChoiceBoolAndNullFalseBecomesTrueFalse(): void
    {
        $this->addRow(1, 'flare_bool', ['intrinsic' => '0', 'boolMode' => 'ternary', 'preselect' => 'false']);
        $this->addRow(2, 'flare_bool', [
            'intrinsic' => '0',
            'boolMode' => 'binary',
            'preselect' => 'null',
            'ctl_boolBinaryChoices' => 'null_false',
        ]);
        $this->addRow(3, 'flare_bool', ['intrinsic' => '0', 'boolMode' => '', 'preselect' => 'TRUE']);
        $this->addRow(4, 'flare_bool', ['intrinsic' => '1', 'preselect' => '1']);

        $this->migration()->run();

        self::assertSame('flare_choice_bool', $this->rows[1]['control']);
        self::assertSame('0', $this->rows[1]['value']);

        self::assertSame('flare_checkbox', $this->rows[2]['control']);
        self::assertSame('true_false', $this->rows[2]['ctl_boolBinaryChoices']);
        self::assertNull($this->rows[2]['value'], '"null" is no value');

        // An unset mode was binary.
        self::assertSame('flare_checkbox', $this->rows[3]['control']);
        self::assertSame('1', $this->rows[3]['value']);

        // Intrinsic bools keep no control and store their fixed value.
        self::assertSame('', $this->rows[4]['control']);
        self::assertSame('1', $this->rows[4]['value']);
    }

    public function testNullFalseIsConvertedOnAnOtherwiseMigratedRow(): void
    {
        $this->addRow(1, 'flare_bool', [
            'intrinsic' => '0',
            'control' => 'flare_checkbox',
            'ctl_boolBinaryChoices' => 'null_false',
        ]);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([[['ctl_boolBinaryChoices' => 'true_false'], ['id' => 1]]], $this->updates);
    }

    public function testSingleDcaSelectPlaceholderBecomesEmptyOptionLabel(): void
    {
        $this->addRow(1, 'flare_dcaSelectField', [
            'intrinsic' => '0',
            'preselect' => 'red',
            'ctl_placeholder' => 'Any colour',
        ]);

        $this->migration()->run();

        self::assertSame([[
            [
                'control' => 'flare_choice',
                'value' => \serialize(['red']),
                'ctl_hasEmptyOption' => '1',
                'ctl_formatEmptyOption' => 'custom',
                'ctl_formatEmptyOptionCustom' => 'Any colour',
                'ctl_placeholder' => '',
            ],
            ['id' => 1],
        ]], $this->updates);
    }

    public function testSingleSelectWithoutPlaceholderKeepsItsFallbackLabel(): void
    {
        $this->addRow(1, 'cfg_tags_choice', ['intrinsic' => '0', 'preselect' => \serialize([3, 7])]);
        $this->addRow(2, 'flare_dcaSelectField', ['intrinsic' => '0', 'ctl_isMandatory' => '1']);

        $this->migration()->run();

        self::assertSame('flare_choice', $this->rows[1]['control']);
        self::assertSame(\serialize(['3', '7']), $this->rows[1]['value']);
        self::assertSame('1', $this->rows[1]['ctl_hasEmptyOption']);
        self::assertSame('empty_option.no_selection', $this->rows[1]['ctl_formatEmptyOption']);
        self::assertSame('', $this->rows[1]['ctl_formatEmptyOptionCustom']);

        self::assertSame('1', $this->rows[2]['ctl_hasEmptyOption']);
        self::assertSame('empty_option.prompt', $this->rows[2]['ctl_formatEmptyOption']);
    }

    public function testMultipleOrExpandedDcaSelectKeepsItsPlaceholder(): void
    {
        $this->addRow(1, 'flare_dcaSelectField', [
            'intrinsic' => '0',
            'ctl_isMultiple' => '1',
            'preselect' => \serialize(['red', 'blue']),
            'ctl_placeholder' => 'Colours',
        ]);
        $this->addRow(2, 'cfg_tags_choice', [
            'intrinsic' => '0',
            'ctl_isExpanded' => '1',
            'ctl_placeholder' => 'Tags',
        ]);

        $this->migration()->run();

        self::assertSame(\serialize(['red', 'blue']), $this->rows[1]['value']);

        foreach ([1 => 'Colours', 2 => 'Tags'] as $id => $placeholder)
        {
            self::assertSame('flare_choice', $this->rows[$id]['control']);
            self::assertSame('0', $this->rows[$id]['ctl_hasEmptyOption']);
            self::assertSame($placeholder, $this->rows[$id]['ctl_placeholder']);
        }
    }

    public function testFieldValueChoiceSingleSelectGetsAnEmptyOption(): void
    {
        $this->addRow(1, 'flare_fieldValueChoice', ['intrinsic' => '0', 'preselect' => 'berlin']);
        $this->addRow(2, 'flare_fieldValueChoice', [
            'intrinsic' => '0',
            'ctl_isMultiple' => '1',
            'preselect' => \serialize(['berlin', 'hamburg']),
        ]);
        $this->addRow(3, 'flare_fieldValueChoice', ['intrinsic' => '1', 'preselect' => \serialize(['berlin'])]);

        $this->migration()->run();

        self::assertSame('flare_choice', $this->rows[1]['control']);
        self::assertSame(\serialize(['berlin']), $this->rows[1]['value']);
        self::assertSame('1', $this->rows[1]['ctl_hasEmptyOption']);

        self::assertSame(\serialize(['berlin', 'hamburg']), $this->rows[2]['value']);
        self::assertSame('0', $this->rows[2]['ctl_hasEmptyOption']);

        self::assertSame('', $this->rows[3]['control']);
        self::assertSame(\serialize(['berlin']), $this->rows[3]['value']);
    }

    public function testDateTypesGetTheirControlAndNoValue(): void
    {
        $this->addRow(1, 'flare_dateRange', ['intrinsic' => '0', 'preselect' => 'leftover']);
        $this->addRow(2, 'flare_calendar_current', ['intrinsic' => '0']);
        $this->addRow(3, 'flare_calendar_current', ['intrinsic' => '1']);

        $this->migration()->run();

        self::assertSame([
            [['control' => 'flare_date_range'], ['id' => 1]],
            [['control' => 'flare_date_range'], ['id' => 2]],
        ], $this->updates);
    }

    public function testElementsWithoutAControlAreNeverPending(): void
    {
        $this->addRow(1, 'flare_published', ['intrinsic' => '0', 'preselect' => 'leftover', 'prefill' => 'x']);
        $this->addRow(2, 'flare_simple_equation', ['intrinsic' => '0']);

        self::assertFalse($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([], $this->updates);
    }

    public function testExistingTargetsAreNotOverwritten(): void
    {
        $this->addRow(1, 'flare_dcaSelectField', [
            'intrinsic' => '0',
            'control' => 'flare_choice',
            'value' => \serialize(['blue']),
            'preselect' => 'red',
        ]);
        $this->addRow(2, 'flare_dcaSelectField', [
            'intrinsic' => '0',
            'value' => \serialize(['blue']),
            'preselect' => 'red',
        ]);

        $this->migration()->run();

        self::assertSame(\serialize(['blue']), $this->rows[1]['value']);
        self::assertSame(\serialize(['blue']), $this->rows[2]['value']);
        self::assertSame('flare_choice', $this->rows[2]['control']);
    }

    public function testShouldRunIsFalseOnceIntrinsicIsGone(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0', 'preselect' => \serialize(['tl_news_archive.5'])]);

        $columns = \array_values(\array_diff(self::COLUMNS, ['intrinsic']));

        self::assertFalse($this->migration($columns)->shouldRun());
        self::assertFalse($this->migration(null)->shouldRun(), 'no table');
    }

    public function testShouldRunWaitsForTheRenamedColumns(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0']);

        $columns = \array_map(
            static fn (string $column): string => $column === 'ctl_placeholder' ? 'placeholder' : $column,
            self::COLUMNS,
        );

        self::assertFalse($this->migration($columns)->shouldRun());
        self::assertTrue($this->migration()->shouldRun());
    }

    public function testShouldRunWaitsForTheTargetColumns(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0']);

        self::assertFalse($this->migration(\array_values(\array_diff(self::COLUMNS, ['control'])))->shouldRun());
        self::assertFalse($this->migration(\array_values(\array_diff(self::COLUMNS, ['value'])))->shouldRun());
    }

    public function testShouldRunReadsMissingOptionalSourcesAsEmpty(): void
    {
        $this->addRow(1, 'flare_bool', ['intrinsic' => '0']);
        unset($this->rows[1]['boolMode'], $this->rows[1]['preselect'], $this->rows[1]['prefill']);

        $columns = \array_values(\array_diff(self::COLUMNS, ['boolMode', 'preselect', 'prefill']));

        self::assertTrue($this->migration($columns)->shouldRun());

        $this->migration($columns)->run();

        self::assertSame([[['control' => 'flare_checkbox'], ['id' => 1]]], $this->updates);
    }

    public function testShouldRunGoesQuietAfterARun(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0', 'preselect' => \serialize(['tl_news_archive.5'])]);
        $this->addRow(2, 'flare_archive', ['intrinsic' => '1', 'preselect' => \serialize(['tl_news_archive.5'])]);
        $this->addRow(3, 'flare_bool', ['intrinsic' => '1', 'preselect' => 'null']);
        $this->addRow(4, 'flare_bool', ['intrinsic' => '1', 'preselect' => 'false']);
        $this->addRow(5, 'flare_bool', ['intrinsic' => '0', 'ctl_boolBinaryChoices' => 'null_false']);
        $this->addRow(6, 'flare_search_keywords', ['intrinsic' => '1', 'prefill' => 'solar']);
        $this->addRow(7, 'flare_search_keywords', ['intrinsic' => '0', 'prefill' => 'leftover']);
        $this->addRow(8, 'flare_dcaSelectField', ['intrinsic' => '0', 'preselect' => '0']);
        $this->addRow(9, 'flare_dcaSelectField', ['intrinsic' => '1', 'preselect' => \serialize([])]);
        $this->addRow(10, 'cfg_tags_choice', ['intrinsic' => '0', 'ctl_placeholder' => 'Tags']);
        $this->addRow(11, 'flare_calendar_current', ['intrinsic' => '1']);
        $this->addRow(12, 'flare_published', ['intrinsic' => '0']);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertFalse($this->migration()->shouldRun());

        // An intrinsic-only database whose leftovers were never read is not pending at all.
        $this->rows = [];
        $this->addRow(1, 'flare_archive', ['intrinsic' => '1', 'preselect' => \serialize(['tl_news_archive.5'])]);
        $this->addRow(2, 'flare_bool', ['intrinsic' => '1', 'preselect' => 'null']);
        $this->addRow(3, 'flare_search_keywords', ['intrinsic' => '1', 'prefill' => '']);
        $this->addRow(4, 'flare_dateRange', ['intrinsic' => '1']);

        self::assertFalse($this->migration()->shouldRun());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function addRow(int $id, string $type, array $values): void
    {
        $this->rows[$id] = \array_merge([
            'id' => (string) $id,
            'type' => $type,
            'intrinsic' => '0',
            'boolMode' => '',
            'preselect' => null,
            'prefill' => null,
            'control' => '',
            'value' => null,
            'ctl_boolBinaryChoices' => '',
            'ctl_isMandatory' => '0',
            'ctl_isMultiple' => '0',
            'ctl_isExpanded' => '0',
            'ctl_hasEmptyOption' => '0',
            'ctl_formatEmptyOption' => '',
            'ctl_formatEmptyOptionCustom' => '',
            'ctl_placeholder' => '',
        ], $values);
    }

    /**
     * @param list<string>|null $columns The table's columns, or null if the table does not exist.
     */
    private function migration(?array $columns = self::COLUMNS): BackfillFilterValuesMigration
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->with([self::TABLE])->willReturn($columns !== null);
        $schemaManager->method('introspectTable')->with(self::TABLE)->willReturnCallback(
            static fn (): Table => new Table(self::TABLE, \array_map(
                static fn (string $name): Column => new Column($name, Type::getType(Types::STRING)),
                $columns ?? [],
            )),
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchAllAssociative')->willReturnCallback(function (string $sql): array {
            self::assertSame('SELECT * FROM tl_flare_filter ORDER BY id', $sql);

            return \array_values($this->rows);
        });
        $connection->method('update')->willReturnCallback(function (string $table, array $data, array $criteria): int {
            self::assertSame(self::TABLE, $table);

            $this->updates[] = [$data, $criteria];
            $this->rows[(int) $criteria['id']] = \array_merge($this->rows[(int) $criteria['id']], $data);

            return 1;
        });
        $connection->expects(self::never())->method('executeStatement');

        return new BackfillFilterValuesMigration($connection);
    }
}
