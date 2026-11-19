<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\Contao\DcaContext;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBounds;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\TimeWindowFilterElement;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Predicate\TimeWindowPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Filter\Proposition;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\FilterModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use PHPUnit\Framework\TestCase;

final class TimeWindowFilterElementTest extends TestCase
{
    use BuildsFilterEffectsTrait;
    use StubsContaoDcaTrait;

    private const BASE = [
        'start_at' => null,
        'stop_at' => null,
        'start_field' => 'startTime',
        'stop_field' => null,
        'overlap' => false,
        'use_recurrence' => false,
        'recurring_field' => null,
        'recurrences_field' => null,
        'repeat_end_field' => null,
    ];

    private const COLUMN_KEYS = [
        'start_field',
        'stop_field',
        'overlap',
        'use_recurrence',
        'recurring_field',
        'recurrences_field',
        'repeat_end_field',
    ];

    private const RECURRENCE = [
        'use_recurrence' => true,
        'recurring_field' => 'recurring',
        'recurrences_field' => 'recurrences',
        'repeat_end_field' => 'repeatEnd',
    ];

    protected function setUp(): void
    {
        $this->stubDca('tl_test', ['fields' => [
            'startTime' => ['sql' => 'bigint(20) NULL'],
            'endTime' => ['sql' => 'bigint(20) NULL'],
            'recurring' => ['sql' => ['type' => 'boolean']],
            'recurrences' => ['sql' => 'smallint(5) unsigned NOT NULL default 0'],
            'repeatEnd' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
            'startStr' => ['sql' => 'varchar(10) NULL'],
            'day' => ['sql' => 'date NULL'],
            'begins' => ['sql' => ['type' => 'integer', 'name' => 'physical']],
            'virtual' => ['inputType' => 'text'],
        ]]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA']['tl_flare_filter']);
    }

    public function testTheTypeIsTheTimeWindow(): void
    {
        self::assertSame('flare_time_window', TimeWindowFilterElement::TYPE);
    }

    public function testTheBoundAppliesWithoutAValue(): void
    {
        self::assertSame([1000, 2000], $this->range(self::bound(1000, 2000), null));
    }

    public function testTheValueNarrowsWithinTheBound(): void
    {
        self::assertSame([1500, 2000], $this->range(self::bound(1000, 2000), DateRangeValue::tryFrom(1500, 3000)));
        self::assertSame([1000, 1800], $this->range(self::bound(1000, 2000), DateRangeValue::tryFrom(500, 1800)));
    }

    public function testOpenSidesWithoutBound(): void
    {
        self::assertSame([0, DateTimeHelper::maxTimestamp()], $this->range(self::BASE, null));
    }

    public function testAnEmptyIntersectionAborts(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildFilterEffect(
            new TimeWindowFilterElement(),
            ['stop_at' => self::time(1000)] + self::BASE,
            DateRangeValue::tryFrom(2000, null),
        );
    }

    public function testAnInvertedPeriodIsBroken(): void
    {
        $this->assertBroken('errors.time_window.inverted_period', self::bound(2000, 1000));
    }

    public function testChecksRunBeforeAnEmptyIntersectionAborts(): void
    {
        $this->assertBroken(
            'errors.time_window.unknown_column',
            ['start_field' => 'nope'] + self::bound(1000, 2000),
            DateRangeValue::tryFrom(3000, null),
        );
    }

    public function testThePredicateGetsTheColumns(): void
    {
        $config = ['stop_field' => 'endTime', 'overlap' => true] + self::RECURRENCE + self::BASE;
        $proposition = $this->proposition($config);

        self::assertSame('main', $proposition->targetAlias);
        self::assertSame([
            'start_field' => 'startTime',
            'stop_field' => 'endTime',
            'overlap' => true,
            'recurring_field' => 'recurring',
            'recurrences_field' => 'recurrences',
            'repeat_end_field' => 'repeatEnd',
            'cast_fields' => [],
        ], self::pick($proposition->options, [
            'start_field',
            'stop_field',
            'overlap',
            'recurring_field',
            'recurrences_field',
            'repeat_end_field',
            'cast_fields',
        ]));
    }

    public function testThePredicateRunsOnTheMainTable(): void
    {
        $element = new TimeWindowFilterElement();
        $context = $this->buildElementContext($element, self::BASE);
        $context = new ElementContext(list: $context->list, filter: $context->filter->withTargetAlias('archive'));

        $propositions = $this->createFilterEffectFactory()->create($context, null)->formula->propositions;

        self::assertCount(1, $propositions);
        self::assertSame('main', $propositions[0]->targetAlias);
    }

    public function testThePhysicalNameIsChecked(): void
    {
        $options = $this->proposition(['start_field' => 'physical'] + self::BASE)->options;

        self::assertSame('physical', $options['start_field']);

        $this->assertBroken('errors.time_window.unknown_column', ['start_field' => 'begins'] + self::BASE);
    }

    public function testOverlapIsOnlyPassedWithAnEndColumn(): void
    {
        $options = $this->proposition(['overlap' => true] + self::BASE)->options;

        self::assertFalse($options['overlap']);
        self::assertNull($options['stop_field']);
    }

    public function testRecurrenceIsOnlyPassedWhenSwitchedOn(): void
    {
        $options = $this->proposition([
            'use_recurrence' => false,
            'recurring_field' => 'nope',
            'recurrences_field' => 'nope',
            'repeat_end_field' => 'nope',
        ] + self::BASE)->options;

        self::assertNull($options['recurring_field']);
        self::assertNull($options['recurrences_field']);
        self::assertNull($options['repeat_end_field']);
    }

    public function testOtherColumnsAreCast(): void
    {
        $options = $this->proposition(['start_field' => 'startStr'] + self::BASE)->options;

        self::assertSame(['startStr'], $options['cast_fields']);
    }

    public function testAnEmptyStartIsBroken(): void
    {
        $this->assertBroken('errors.time_window.no_column', ['start_field' => null] + self::BASE);
    }

    /**
     * @dataProvider provideUnknownColumns
     */
    public function testAnUnknownColumnIsBroken(string $column): void
    {
        $this->assertBroken('errors.time_window.unknown_column', ['start_field' => $column] + self::BASE);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownColumns(): iterable
    {
        yield 'not in the DCA' => ['nope'];
        yield 'without sql' => ['virtual'];
    }

    public function testADateColumnIsBroken(): void
    {
        $this->assertBroken('errors.time_window.date_column', ['start_field' => 'day'] + self::BASE);
    }

    public function testRecurrenceWithAMissingColumnIsBroken(): void
    {
        $this->assertBroken(
            'errors.time_window.no_column',
            ['repeat_end_field' => null] + self::RECURRENCE + self::BASE,
        );
    }

    public function testTouchingBoundsDoNotAbort(): void
    {
        self::assertSame([1000, 1000], $this->range(self::bound(1000, 1000), null));
    }

    public function testWrongValueClassIsRejected(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterEffect(new TimeWindowFilterElement(), self::BASE, new BoolValue(true));
    }

    public function testDateBoundsAreTheConfiguredPeriod(): void
    {
        $element = new TimeWindowFilterElement();

        $bounds = $element->getDateBounds($this->buildElementContext($element, self::bound(1000, 2000)));

        self::assertSame([1000, 2000], [$bounds->min?->getTimestamp(), $bounds->max?->getTimestamp()]);
    }

    public function testNoBoundGivesOpenDateBounds(): void
    {
        $element = new TimeWindowFilterElement();

        self::assertEquals(new DateBounds(), $element->getDateBounds($this->buildElementContext($element, self::BASE)));
    }

    public function testATimeSpanSideReadsItsExpression(): void
    {
        $expected = new \DateTimeImmutable('monday this week', DateTimeHelper::getTimeZone());

        $config = $this->transform(['elm_configureStart' => 'this_week', 'elm_startAt' => 'monday this week']);

        self::assertInstanceOf(\DateTimeImmutable::class, $config['start_at']);
        self::assertSame($expected->getTimestamp(), $config['start_at']->getTimestamp());
    }

    public function testAStrSideReadsTheEditorsExpression(): void
    {
        $config = $this->transform(['elm_configureStart' => 'str', 'elm_startAt' => '2026-01-01']);

        self::assertSame(\strtotime('2026-01-01'), $config['start_at']?->getTimestamp());
    }

    public function testADateSideReadsTheBareTimestamp(): void
    {
        $config = $this->transform(['elm_configureStop' => 'date', 'elm_stopAt' => '1600432000']);

        self::assertSame(1600432000, $config['stop_at']?->getTimestamp());
    }

    public function testAStrSideKeepsReadingATimestampLeftFromDateMode(): void
    {
        $config = $this->transform(['elm_configureStart' => 'str', 'elm_startAt' => '1600432000']);

        self::assertSame(1600432000, $config['start_at']?->getTimestamp());
    }

    public function testAnUnconfiguredSideIsOpen(): void
    {
        $config = $this->transform([
            'elm_configureStart' => 'str',
            'elm_startAt' => 'today',
            'elm_configureStop' => '',
            'elm_stopAt' => '+1 month',
        ]);

        self::assertNotNull($config['start_at']);
        self::assertNull($config['stop_at']);
    }

    public function testAnEmptyOrBlankConfiguredSideIsOpen(): void
    {
        $config = $this->transform([
            'elm_configureStart' => 'str',
            'elm_startAt' => '',
            'elm_configureStop' => 'date',
            'elm_stopAt' => '   ',
        ]);

        self::assertNull($config['start_at']);
        self::assertNull($config['stop_at']);
    }

    public function testUnreadableTextFailsClosed(): void
    {
        $this->expectException(FilterException::class);

        $this->transform(['elm_configureStart' => 'str', 'elm_startAt' => 'next blursday']);
    }

    public function testTheTransformReadsTheColumns(): void
    {
        $config = $this->transform([
            'elm_periodStartField' => 'startTime',
            'elm_periodStopField' => 'endTime',
            'elm_overlap' => '1',
            'elm_useRecurrence' => '1',
            'elm_recurringField' => 'recurring',
            'elm_recurrencesField' => 'recurrences',
            'elm_repeatEndField' => 'repeatEnd',
        ]);

        self::assertSame([
            'start_field' => 'startTime',
            'stop_field' => 'endTime',
            'overlap' => true,
            'use_recurrence' => true,
            'recurring_field' => 'recurring',
            'recurrences_field' => 'recurrences',
            'repeat_end_field' => 'repeatEnd',
        ], self::pick($config, self::COLUMN_KEYS));

        $config = $this->transform([
            'elm_periodStartField' => '',
            'elm_periodStopField' => '',
            'elm_overlap' => '',
            'elm_useRecurrence' => '',
            'elm_recurringField' => '',
            'elm_recurrencesField' => '',
            'elm_repeatEndField' => '',
        ]);

        self::assertSame([
            'start_field' => null,
            'stop_field' => null,
            'overlap' => false,
            'use_recurrence' => false,
            'recurring_field' => null,
            'recurrences_field' => null,
            'repeat_end_field' => null,
        ], self::pick($config, self::COLUMN_KEYS));
    }

    public function testTheTransformSatisfiesTheSchema(): void
    {
        $element = new TimeWindowFilterElement();

        $config = $this->transform([
            'elm_configureStart' => 'str',
            'elm_startAt' => '2026-01-01',
            'elm_periodStartField' => 'startTime',
            'elm_periodStopField' => 'endTime',
            'elm_overlap' => '1',
            'elm_useRecurrence' => '',
        ]);

        $context = $this->buildElementContext($element, $config);

        self::assertSame(\strtotime('2026-01-01'), $context->get('start_at')?->getTimestamp());
        self::assertNull($context->get('stop_at'));
        self::assertSame('startTime', $context->get('start_field'));
        self::assertSame('endTime', $context->get('stop_field'));
        self::assertTrue($context->get('overlap'));
        self::assertFalse($context->get('use_recurrence'));
        self::assertNull($context->get('recurring_field'));
    }

    public function testDateSidesGetTheDateTimeInput(): void
    {
        $this->applyDca(['elm_configureStart' => 'date', 'elm_configureStop' => 'str']);

        $fields = $GLOBALS['TL_DCA']['tl_flare_filter']['fields'];

        self::assertSame('datim', $fields['elm_startAt']['eval']['rgxp'] ?? null);
        self::assertArrayNotHasKey('rgxp', $fields['elm_stopAt']['eval']);
    }

    public function testThePaletteListsTheColumnPickers(): void
    {
        $this->applyDca([]);

        self::assertSame(
            '{date_start_legend},elm_configureStart;{date_stop_legend},elm_configureStop;'
            . '{date_columns_legend},elm_periodStartField,elm_periodStopField,elm_useRecurrence',
            $GLOBALS['TL_DCA']['tl_flare_filter']['palettes'][TimeWindowFilterElement::TYPE],
        );
    }

    public function testOverlapAppearsWithAnEndColumn(): void
    {
        $this->applyDca(['elm_periodStopField' => 'endTime']);

        self::assertSame(
            '{date_start_legend},elm_configureStart;{date_stop_legend},elm_configureStop;'
            . '{date_columns_legend},elm_periodStartField,elm_periodStopField,elm_overlap,elm_useRecurrence',
            $GLOBALS['TL_DCA']['tl_flare_filter']['palettes'][TimeWindowFilterElement::TYPE],
        );
    }

    public function testRecurrencePickersAreItsSubpalette(): void
    {
        $this->applyDca([]);

        $dca = $GLOBALS['TL_DCA']['tl_flare_filter'];

        self::assertContains('elm_useRecurrence', $dca['palettes']['__selector__'] ?? []);
        self::assertSame(
            'elm_recurringField,elm_recurrencesField,elm_repeatEndField',
            $dca['subpalettes']['elm_useRecurrence'] ?? null,
        );
    }

    public function testTheSaveCallbackRejectsUnreadableText(): void
    {
        $this->applyDca(['elm_configureStart' => 'str', 'elm_configureStop' => 'str']);

        foreach (['elm_startAt', 'elm_stopAt'] as $field)
        {
            $save = $GLOBALS['TL_DCA']['tl_flare_filter']['fields'][$field]['save_callback'][0] ?? null;
            self::assertIsCallable($save, $field);

            self::assertSame('', $save(''));
            self::assertSame('today', $save('today'));

            try {
                $save('next blursday');
                self::fail("{$field}: unreadable text must be rejected");
            } catch (\RuntimeException $e) {
                self::assertSame('filter.value.date_range.invalid', $e->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array{int, int}
     */
    private function range(array $config, ?DateRangeValue $value): array
    {
        $proposition = $this->proposition($config, $value);

        return [$proposition->options['start'], $proposition->options['stop']];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function proposition(array $config, ?DateRangeValue $value = null): Proposition
    {
        $propositions = $this->buildFilterEffect(new TimeWindowFilterElement(), $config, $value)
            ->formula
            ->propositions;

        self::assertCount(1, $propositions);
        self::assertSame(TimeWindowPredicate::class, $propositions[0]->predicateClass);

        return $propositions[0];
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $keys
     * @return array<string, mixed> The values of the keys, in the order of the keys.
     */
    private static function pick(array $values, array $keys): array
    {
        $picked = [];

        foreach ($keys as $key)
        {
            self::assertArrayHasKey($key, $values);
            $picked[$key] = $values[$key];
        }

        return $picked;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function assertBroken(string $message, array $config, ?ValueInterface $value = null): void
    {
        try {
            $this->buildFilterEffect(new TimeWindowFilterElement(), $config, $value);
            self::fail('The filter must be broken');
        } catch (FilterException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function bound(int $start, int $stop): array
    {
        return ['start_at' => self::time($start), 'stop_at' => self::time($stop)] + self::BASE;
    }

    private static function time(int $timestamp): \DateTimeImmutable
    {
        return new \DateTimeImmutable("@{$timestamp}");
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function transform(array $row): array
    {
        $transformers = new TransformerMap();
        (new TimeWindowFilterElement())->configureTransformers($transformers);

        $transformer = $transformers->find($model = new FilterModelFixture($row));
        self::assertNotNull($transformer);

        $transformer($config = new ConfigBuilder(), $model);

        return $config->all();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applyDca(array $row): void
    {
        $GLOBALS['TL_DCA']['tl_flare_filter'] = [
            'palettes' => ['__prefix__' => '', '__suffix__' => ''],
            'fields' => [
                'elm_startAt' => ['inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50']],
                'elm_stopAt' => ['inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50']],
            ],
        ];

        $builder = new DcaBuilder();

        (new TimeWindowFilterElement())->buildDca($builder, new DcaContext(
            table: 'tl_flare_filter',
            type: TimeWindowFilterElement::TYPE,
            listModel: new ListModelFixture(['id' => 1, 'dc' => 'tl_calendar_events']),
            filterModel: new FilterModelFixture(['id' => 7, 'pid' => 1] + $row),
            executionContextFactory: static fn () => null,
        ));

        $builder->apply('tl_flare_filter', TimeWindowFilterElement::TYPE);
    }
}
