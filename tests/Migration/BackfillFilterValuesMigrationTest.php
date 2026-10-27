<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Migration;

use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
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
        'ctl_formatEmptyOptionCustom', 'ctl_placeholder', 'elm_whitelistParents', 'elm_groupWhitelistParents',
    ];

    private const MARKER = 'legacyBackfilled';

    /** @var list<string>|null The table's columns, or null if the table does not exist. */
    private ?array $columns = self::COLUMNS;

    /** The marker column's current default. */
    private string $markerDefault = '0';

    /** @var array<int, array<string, mixed>> The table's rows by id; `update()` calls are applied to them. */
    private array $rows = [];

    /** @var list<array{array<string, mixed>, array<string, mixed>}> The recorded `update()` calls: data, criteria. */
    private array $updates = [];

    /** @var list<string> The recorded `executeStatement()` calls. */
    private array $statements = [];

    /** @var array<int, array<string, mixed>> The `tl_flare_list` rows by id. */
    private array $lists = [];

    /** @var list<string> The data containers loaded through the framework. */
    private array $loadedDcas = [];

    protected function setUp(): void
    {
        $this->columns = self::COLUMNS;
        $this->markerDefault = '0';
        $this->rows = [];
        $this->updates = [];
        $this->statements = [];
        $this->lists = [1 => ['id' => '1', 'type' => 'flare_generic_dc', 'dc' => 'tl_news']];
        $this->loadedDcas = [];
        $GLOBALS['TL_DCA'] = ['tl_news' => ['config' => ['ptable' => 'tl_news_archive']]];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA']);
    }

    public function testRunAddsTheMarkerMarksEveryRowAndDefaultsItToDone(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0']);
        $this->addRow(2, 'flare_published', ['intrinsic' => '0']);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([
            "ALTER TABLE tl_flare_filter ADD legacyBackfilled CHAR(1) NOT NULL DEFAULT '0'",
            "ALTER TABLE tl_flare_filter ALTER legacyBackfilled SET DEFAULT '1'",
        ], $this->statements);
        self::assertContains(self::MARKER, $this->columns);
        self::assertSame('1', $this->rows[1][self::MARKER]);
        self::assertSame('1', $this->rows[2][self::MARKER], 'types without a control are marked as well');
        self::assertFalse($this->migration()->shouldRun());

        // A second run does not add the column again; switching the default is idempotent.
        $this->statements = [];
        $this->migration()->run();

        self::assertSame(["ALTER TABLE tl_flare_filter ALTER legacyBackfilled SET DEFAULT '1'"], $this->statements);
    }

    public function testRunResumesAnInterruptedRun(): void
    {
        // Interrupted between the ADD and the default switch: the column still defaults to '0'.
        $this->columns[] = self::MARKER;
        $this->markerDefault = '0';
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0', self::MARKER => '1', 'control' => 'flare_choice']);
        $this->addRow(2, 'flare_archive', ['intrinsic' => '0', self::MARKER => '0']);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame(
            ["ALTER TABLE tl_flare_filter ALTER legacyBackfilled SET DEFAULT '1'"],
            $this->statements,
            'no second ADD, but the default is switched',
        );
        self::assertSame('1', $this->markerDefault);
        self::assertSame([[['control' => 'flare_choice', self::MARKER => '1'], ['id' => 2]]], $this->rowUpdates());
        self::assertFalse($this->migration()->shouldRun());
    }

    public function testShouldRunWhileTheMarkerIsAbsent(): void
    {
        self::assertTrue($this->migration()->shouldRun(), 'empty table');

        $this->addRow(1, 'flare_published', ['intrinsic' => '0']);

        self::assertTrue($this->migration()->shouldRun());
    }

    public function testRowsCreatedLaterAreNotPending(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0']);
        $this->migration()->run();

        // The retired `intrinsic` column keeps its default, the control is empty: an editor's new intrinsic filter.
        $this->addRow(2, 'flare_archive', ['intrinsic' => '0', 'preselect' => \serialize(['tl_news_archive.5'])]);

        self::assertSame('1', $this->rows[2][self::MARKER]);
        self::assertFalse($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame('', $this->rows[2]['control']);
        self::assertNull($this->rows[2]['value']);
    }

    public function testEditorChangesAfterTheRunAreNotReverted(): void
    {
        $this->addRow(1, 'flare_dcaSelectField', ['intrinsic' => '0', 'preselect' => 'red']);
        $this->addRow(2, 'flare_search_keywords', ['intrinsic' => '1', 'prefill' => 'solar']);
        $this->migration()->run();

        self::assertSame('flare_choice', $this->rows[1]['control']);
        self::assertSame('solar', $this->rows[2]['value']);

        // The editor makes the first filter intrinsic and clears both values.
        $this->rows[1]['control'] = '';
        $this->rows[1]['value'] = null;
        $this->rows[2]['value'] = null;

        self::assertFalse($this->migration()->shouldRun());

        $this->updates = [];
        $this->migration()->run();

        self::assertSame([], $this->rowUpdates());
        self::assertSame('', $this->rows[1]['control']);
        self::assertNull($this->rows[1]['value']);
        self::assertNull($this->rows[2]['value']);
    }

    public function testIntrinsicOnlyDatabaseGetsItsFixedValuesOnce(): void
    {
        $this->addRow(1, 'flare_bool', ['intrinsic' => '1', 'preselect' => 'true']);
        $this->addRow(2, 'flare_fieldValueChoice', ['intrinsic' => '1', 'preselect' => \serialize(['berlin'])]);
        $this->addRow(3, 'flare_archive', ['intrinsic' => '1', 'preselect' => \serialize(['tl_news_archive.5'])]);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([
            [['value' => '1', self::MARKER => '1'], ['id' => 1]],
            [['value' => \serialize(['berlin']), self::MARKER => '1'], ['id' => 2]],
            [[self::MARKER => '1'], ['id' => 3]],
        ], $this->rowUpdates());
        self::assertSame('', $this->rows[1]['control']);
        self::assertFalse($this->migration()->shouldRun());
    }

    public function testIntrinsicArchiveIgnoresLeftoverPreselect(): void
    {
        $this->addRow(1, 'flare_archive', [
            'intrinsic' => '1',
            'preselect' => \serialize(['tl_news_archive.5']),
            'elm_whitelistParents' => \serialize(['3']),
        ]);

        $this->migration()->run();

        self::assertSame('', $this->rows[1]['control']);
        self::assertSame(
            \serialize(['tl_news_archive.3']),
            $this->rows[1]['value'],
            'the whitelist, not the preselect',
        );
    }

    public function testIntrinsicArchiveWhitelistBecomesItsValue(): void
    {
        $this->addRow(1, 'flare_archive', [
            'intrinsic' => '1',
            'elm_whitelistParents' => \serialize(['3', '5', '5', '0']),
            'elm_useWhitelistForOptionsOnly' => '1',
        ]);

        $this->migration()->run();

        self::assertSame(\serialize(['tl_news_archive.3', 'tl_news_archive.5']), $this->rows[1]['value']);
        self::assertSame('', $this->rows[1]['control']);
    }

    public function testIntrinsicArchiveOfADynamicListUsesItsGroups(): void
    {
        $this->lists[2] = ['id' => '2', 'type' => 'flare_generic_dc', 'dc' => 'tl_content'];
        $GLOBALS['TL_DCA']['tl_content'] = ['config' => ['dynamicPtable' => true]];
        $this->addRow(1, 'flare_archive', [
            'pid' => '2',
            'intrinsic' => '1',
            'elm_whitelistParents' => \serialize(['99']),
            'elm_groupWhitelistParents' => \serialize([
                ['tablePtable' => 'tl_news', 'whitelistParents' => \serialize(['1'])],
                ['tablePtable' => 'tl_calendar', 'whitelistParents' => \serialize(['7'])],
                ['tablePtable' => '', 'whitelistParents' => \serialize(['8'])],
            ]),
        ]);

        $this->migration()->run();

        self::assertSame(\serialize(['tl_news.1', 'tl_calendar.7']), $this->rows[1]['value']);
    }

    public function testIntrinsicArchiveWithoutWhitelistGetsNoValue(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '1', 'elm_whitelistParents' => \serialize([])]);

        $this->migration()->run();

        self::assertNull($this->rows[1]['value']);
    }

    public function testShouldRunWaitsForTheRenamedElementColumns(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '1']);
        $this->columns = \array_values(\array_diff($this->columns, ['elm_whitelistParents']));

        self::assertFalse($this->migration()->shouldRun());
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
            ['control' => 'flare_choice', 'value' => \serialize(['tl_news_archive.5']), self::MARKER => '1'],
            ['id' => 1],
        ]], $this->rowUpdates());

        // Archive keeps its empty option and placeholder settings 1:1.
        self::assertSame('Pick one', $this->rows[1]['ctl_placeholder']);
        self::assertSame('0', $this->rows[1]['ctl_hasEmptyOption']);
    }

    public function testControlledArchiveQualifiesBareIdsWithThePtableOfTheListsDataContainer(): void
    {
        $this->addRow(1, 'flare_archive', [
            'intrinsic' => '0',
            'preselect' => \serialize(['5', 6, 'tl_news_archive.7', 'tl_news_archive.5']),
        ]);

        $result = $this->migration()->run();

        self::assertSame(
            \serialize(['tl_news_archive.5', 'tl_news_archive.6', 'tl_news_archive.7']),
            $this->rows[1]['value'],
        );
        self::assertSame(['tl_news'], $this->loadedDcas);
        self::assertStringNotContainsString('dropped', $result->getMessage());
    }

    public function testNewsAndEventListsUseTheirDriversDataContainer(): void
    {
        $this->lists = [
            1 => ['id' => '1', 'type' => 'flare_news', 'dc' => ''],
            2 => ['id' => '2', 'type' => 'flare_events', 'dc' => 'tl_ignored'],
        ];
        $GLOBALS['TL_DCA']['tl_calendar_events'] = ['config' => ['ptable' => 'tl_calendar']];
        $this->addRow(1, 'flare_archive', ['pid' => '1', 'intrinsic' => '0', 'preselect' => \serialize(['5'])]);
        $this->addRow(2, 'flare_archive', ['pid' => '2', 'intrinsic' => '0', 'preselect' => '3']);

        $this->migration()->run();

        self::assertSame(\serialize(['tl_news_archive.5']), $this->rows[1]['value']);
        self::assertSame(\serialize(['tl_calendar.3']), $this->rows[2]['value']);
        self::assertSame(['tl_news', 'tl_calendar_events'], $this->loadedDcas);
    }

    public function testArchiveKeysTheOldElementCouldNotReadAreDroppedAndCounted(): void
    {
        $this->lists[2] = ['id' => '2', 'type' => 'flare_generic_dc', 'dc' => 'tl_without_ptable'];
        $GLOBALS['TL_DCA']['tl_without_ptable'] = ['config' => ['dynamicPtable' => true]];
        $this->addRow(1, 'flare_archive', [
            'pid' => '2',
            'intrinsic' => '0',
            'preselect' => \serialize(['5', 'tl_news_archive.7']),
        ]);
        $this->addRow(2, 'flare_archive', [
            'intrinsic' => '0',
            'preselect' => \serialize(['tl_news_archive.0', 'unreadable', 'tl_news_archive.08']),
        ]);
        $this->addRow(3, 'flare_archive', ['pid' => '99', 'intrinsic' => '0', 'preselect' => '5']);

        $result = $this->migration()->run();

        self::assertSame(\serialize(['tl_news_archive.7']), $this->rows[1]['value']);
        self::assertSame(\serialize(['tl_news_archive.8']), $this->rows[2]['value']);
        self::assertNull($this->rows[3]['value'], 'a filter of a missing list has no ptable');
        self::assertStringContainsString('4 archive preselect entries dropped', $result->getMessage());
    }

    public function testNoDataContainerIsLoadedForQualifiedPreselects(): void
    {
        // Controlled rows with qualified keys need no ptable; intrinsic archive rows always do (their whitelist).
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0', 'preselect' => \serialize(['tl_news_archive.5'])]);
        $this->addRow(2, 'flare_archive', ['intrinsic' => '0', 'preselect' => 'tl_news_archive.7']);

        $this->migration()->run();

        self::assertSame([], $this->loadedDcas);
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

        self::assertSame(
            [[['ctl_boolBinaryChoices' => 'true_false', self::MARKER => '1'], ['id' => 1]]],
            $this->rowUpdates(),
        );
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
                self::MARKER => '1',
            ],
            ['id' => 1],
        ]], $this->rowUpdates());
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

    public function testMultipleOrExpandedMandatorySelectKeepsItsPlaceholder(): void
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
            'ctl_isMandatory' => '1',
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

    public function testExpandedNonMandatorySelectKeepsItsNoSelectionRadio(): void
    {
        $this->addRow(1, 'cfg_tags_choice', [
            'intrinsic' => '0',
            'ctl_isExpanded' => '1',
            'ctl_placeholder' => 'Any tag',
        ]);
        $this->addRow(2, 'flare_dcaSelectField', ['intrinsic' => '0', 'ctl_isExpanded' => '1']);

        $this->migration()->run();

        self::assertSame('1', $this->rows[1]['ctl_hasEmptyOption']);
        self::assertSame('custom', $this->rows[1]['ctl_formatEmptyOption']);
        self::assertSame('Any tag', $this->rows[1]['ctl_formatEmptyOptionCustom']);
        self::assertSame('', $this->rows[1]['ctl_placeholder']);

        self::assertSame('1', $this->rows[2]['ctl_hasEmptyOption']);
        self::assertSame('empty_option.no_selection', $this->rows[2]['ctl_formatEmptyOption']);
    }

    public function testFieldValueChoiceSingleSelectGetsAnEmptyOption(): void
    {
        $this->addRow(1, 'flare_fieldValueChoice', [
            'intrinsic' => '0',
            'preselect' => 'berlin',
            'ctl_formatEmptyOption' => 'empty_option.all',
        ]);
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
        self::assertSame('', $this->rows[1]['ctl_formatEmptyOption'], 'the default label, not a leftover');

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
            [['control' => 'flare_date_range', self::MARKER => '1'], ['id' => 1]],
            [['control' => 'flare_date_range', self::MARKER => '1'], ['id' => 2]],
            [[self::MARKER => '1'], ['id' => 3]],
        ], $this->rowUpdates());
    }

    public function testElementsWithoutAControlAreNeverPending(): void
    {
        $this->columns[] = self::MARKER;
        $this->addRow(1, 'flare_published', ['intrinsic' => '0', 'preselect' => 'leftover', 'prefill' => 'x']);
        $this->addRow(2, 'flare_simple_equation', ['intrinsic' => '0']);

        self::assertFalse($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([], $this->rowUpdates());
        self::assertSame('1', $this->rows[1][self::MARKER]);
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

        self::assertSame([
            [[self::MARKER => '1'], ['id' => 1]],
            [[
                'control' => 'flare_choice',
                'ctl_hasEmptyOption' => '1',
                'ctl_formatEmptyOption' => 'empty_option.no_selection',
                self::MARKER => '1',
            ], ['id' => 2]],
        ], $this->rowUpdates());
        self::assertSame(\serialize(['blue']), $this->rows[1]['value']);
        self::assertSame(\serialize(['blue']), $this->rows[2]['value']);
        self::assertSame('flare_choice', $this->rows[2]['control']);
    }

    public function testShouldRunIsFalseOnceIntrinsicIsGone(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0', 'preselect' => \serialize(['tl_news_archive.5'])]);

        $this->columns = \array_values(\array_diff(self::COLUMNS, ['intrinsic']));

        self::assertFalse($this->migration()->shouldRun());

        $this->columns = null;

        self::assertFalse($this->migration()->shouldRun(), 'no table');
    }

    public function testShouldRunWaitsForTheRenamedColumns(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0']);

        $this->columns = \array_map(
            static fn (string $column): string => $column === 'ctl_placeholder' ? 'placeholder' : $column,
            self::COLUMNS,
        );

        self::assertFalse($this->migration()->shouldRun());

        $this->columns = self::COLUMNS;

        self::assertTrue($this->migration()->shouldRun());
    }

    public function testShouldRunWaitsForTheTargetColumns(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0']);

        foreach (['control', 'value'] as $target)
        {
            $this->columns = \array_values(\array_diff(self::COLUMNS, [$target]));

            self::assertFalse($this->migration()->shouldRun(), $target);
        }
    }

    public function testMissingOptionalSourcesReadAsEmpty(): void
    {
        $this->columns = \array_values(\array_diff(self::COLUMNS, ['boolMode', 'preselect', 'prefill']));
        $this->addRow(1, 'flare_bool', ['intrinsic' => '0']);
        unset($this->rows[1]['boolMode'], $this->rows[1]['preselect'], $this->rows[1]['prefill']);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertSame([[['control' => 'flare_checkbox', self::MARKER => '1'], ['id' => 1]]], $this->rowUpdates());
    }

    public function testShouldRunGoesQuietAfterARun(): void
    {
        $this->addRow(1, 'flare_archive', ['intrinsic' => '0', 'preselect' => \serialize(['tl_news_archive.5'])]);
        $this->addRow(2, 'flare_archive', ['intrinsic' => '1', 'preselect' => \serialize(['tl_news_archive.5'])]);
        $this->addRow(3, 'flare_bool', ['intrinsic' => '1', 'preselect' => 'null']);
        $this->addRow(4, 'flare_bool', ['intrinsic' => '0', 'ctl_boolBinaryChoices' => 'null_false']);
        $this->addRow(5, 'flare_search_keywords', ['intrinsic' => '0', 'prefill' => 'leftover']);
        $this->addRow(6, 'flare_dcaSelectField', ['intrinsic' => '0', 'preselect' => '0']);
        $this->addRow(7, 'cfg_tags_choice', ['intrinsic' => '0', 'ctl_placeholder' => 'Tags']);
        $this->addRow(8, 'flare_calendar_current', ['intrinsic' => '1']);
        $this->addRow(9, 'flare_published', ['intrinsic' => '0']);

        self::assertTrue($this->migration()->shouldRun());

        $this->migration()->run();

        self::assertFalse($this->migration()->shouldRun());

        foreach ($this->rows as $id => $row) {
            self::assertSame('1', $row[self::MARKER], (string) $id);
        }
    }

    /**
     * Adds a row; once the marker column exists, the row gets the column's current default.
     *
     * @param array<string, mixed> $values
     */
    private function addRow(int $id, string $type, array $values): void
    {
        $row = [
            'id' => (string) $id,
            'pid' => '1',
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
            'elm_whitelistParents' => null,
            'elm_groupWhitelistParents' => null,
        ];

        if (\in_array(self::MARKER, $this->columns ?? [], true)) {
            $row[self::MARKER] = $this->markerDefault;
        }

        $this->rows[$id] = \array_merge($row, $values);
    }

    /**
     * The per-row `update()` calls.
     *
     * @return list<array{array<string, mixed>, array<string, mixed>}>
     */
    private function rowUpdates(): array
    {
        return \array_values(\array_filter(
            $this->updates,
            static fn (array $update): bool => isset($update[1]['id']),
        ));
    }

    /**
     * A migration over a fake `tl_flare_filter`: `$this->columns` is its schema, `$this->rows` its content.
     */
    private function migration(): BackfillFilterValuesMigration
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->with([self::TABLE])->willReturnCallback(
            fn (): bool => $this->columns !== null,
        );
        $schemaManager->method('introspectTable')->with(self::TABLE)->willReturnCallback(
            fn (): Table => new Table(self::TABLE, \array_map(
                static fn (string $name): Column => new Column($name, Type::getType(Types::STRING)),
                $this->columns ?? [],
            )),
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchAllAssociative')->willReturnCallback(function (string $sql): array {
            self::assertSame("SELECT * FROM tl_flare_filter WHERE legacyBackfilled = '0' ORDER BY id", $sql);

            return \array_values(\array_filter(
                $this->rows,
                static fn (array $row): bool => $row[self::MARKER] === '0',
            ));
        });
        $connection->method('update')->willReturnCallback(function (string $table, array $data, array $criteria): int {
            self::assertSame(self::TABLE, $table);

            $this->updates[] = [$data, $criteria];
            $affected = 0;

            foreach ($this->rows as $id => $row)
            {
                if (\array_intersect_key($row, $criteria) == $criteria) {
                    $this->rows[$id] = \array_merge($row, $data);
                    ++$affected;
                }
            }

            return $affected;
        });
        $connection->method('fetchAssociative')->willReturnCallback(function (string $sql, array $params): array|false {
            self::assertSame('SELECT * FROM tl_flare_list WHERE id = ?', $sql);

            return $this->lists[$params[0]] ?? false;
        });
        $connection->method('executeStatement')->willReturnCallback(function (string $sql): int {
            $this->statements[] = $sql;

            if ($sql === "ALTER TABLE tl_flare_filter ADD legacyBackfilled CHAR(1) NOT NULL DEFAULT '0'") {
                $this->columns[] = self::MARKER;

                foreach ($this->rows as $id => $row) {
                    $this->rows[$id][self::MARKER] = '0';
                }
            }
            elseif ($sql === "ALTER TABLE tl_flare_filter ALTER legacyBackfilled SET DEFAULT '1'") {
                $this->markerDefault = '1';
            }
            else {
                self::fail('Unexpected statement: ' . $sql);
            }

            return 0;
        });

        $controller = $this->getMockBuilder(Adapter::class)
            ->disableOriginalConstructor()
            ->addMethods(['loadDataContainer'])
            ->getMock();
        $controller->method('loadDataContainer')->willReturnCallback(function (string $table): void {
            $this->loadedDcas[] = $table;
        });

        $framework = $this->createMock(ContaoFramework::class);
        $framework->method('getAdapter')->with(Controller::class)->willReturn($controller);

        return new BackfillFilterValuesMigration($connection, $framework);
    }
}
