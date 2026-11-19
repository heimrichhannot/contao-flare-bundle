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
use HeimrichHannot\FlareBundle\Filter\Element\CalendarCurrentFilterElement;
use HeimrichHannot\FlareBundle\Filter\Predicate\CalendarCurrentPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\FilterModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use PHPUnit\Framework\TestCase;

final class CalendarCurrentFilterElementTest extends TestCase
{
    use BuildsFilterEffectsTrait;

    private const BASE = [
        'start_at' => null,
        'stop_at' => null,
        'has_extended_events' => false,
    ];

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA']['tl_flare_filter']);
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

    /**
     * @dataProvider provideExtendedEvents
     */
    public function testAnEmptyIntersectionAborts(bool $extendedEvents): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildFilterEffect(
            new CalendarCurrentFilterElement(),
            ['stop_at' => self::time(1000), 'has_extended_events' => $extendedEvents] + self::BASE,
            DateRangeValue::tryFrom(2000, null),
        );
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideExtendedEvents(): iterable
    {
        yield 'with extended events' => [true];
        yield 'without extended events' => [false];
    }

    public function testTouchingBoundsDoNotAbort(): void
    {
        self::assertSame([1000, 1000], $this->range(self::bound(1000, 1000), null));
    }

    public function testWrongValueClassIsRejected(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterEffect(new CalendarCurrentFilterElement(), self::BASE, new BoolValue(true));
    }

    public function testDateBoundsAreTheConfiguredPeriod(): void
    {
        $element = new CalendarCurrentFilterElement();

        $bounds = $element->getDateBounds($this->buildElementContext($element, self::bound(1000, 2000)));

        self::assertSame([1000, 2000], [$bounds->min?->getTimestamp(), $bounds->max?->getTimestamp()]);
    }

    public function testNoBoundGivesOpenDateBounds(): void
    {
        $element = new CalendarCurrentFilterElement();

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

    public function testTheTransformSatisfiesTheSchema(): void
    {
        $element = new CalendarCurrentFilterElement();

        $config = $this->transform([
            'elm_configureStart' => 'str',
            'elm_startAt' => '2026-01-01',
            'elm_hasExtendedEvents' => '1',
        ]);

        $context = $this->buildElementContext($element, $config);

        self::assertSame(\strtotime('2026-01-01'), $context->get('start_at')?->getTimestamp());
        self::assertNull($context->get('stop_at'));
        self::assertTrue($context->get('has_extended_events'));
    }

    public function testDateSidesGetTheDateTimeInput(): void
    {
        $this->applyDca(['elm_configureStart' => 'date', 'elm_configureStop' => 'str']);

        $fields = $GLOBALS['TL_DCA']['tl_flare_filter']['fields'];

        self::assertSame('datim', $fields['elm_startAt']['eval']['rgxp'] ?? null);
        self::assertArrayNotHasKey('rgxp', $fields['elm_stopAt']['eval']);
    }

    public function testThePaletteHasNoLimitLegend(): void
    {
        $this->applyDca([]);

        self::assertSame(
            '{date_start_legend},elm_configureStart,elm_hasExtendedEvents;{date_stop_legend},elm_configureStop',
            $GLOBALS['TL_DCA']['tl_flare_filter']['palettes'][CalendarCurrentFilterElement::TYPE],
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
        $propositions = $this->buildFilterEffect(new CalendarCurrentFilterElement(), $config, $value)
            ->formula
            ->propositions;

        self::assertCount(1, $propositions);
        self::assertSame(CalendarCurrentPredicate::class, $propositions[0]->predicateClass);

        return [$propositions[0]->options['start'], $propositions[0]->options['stop']];
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
        (new CalendarCurrentFilterElement())->configureTransformers($transformers);

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

        (new CalendarCurrentFilterElement())->buildDca($builder, new DcaContext(
            table: 'tl_flare_filter',
            type: CalendarCurrentFilterElement::TYPE,
            listModel: new ListModelFixture(['id' => 1, 'dc' => 'tl_calendar_events']),
            filterModel: new FilterModelFixture(['id' => 7, 'pid' => 1] + $row),
            executionContextFactory: static fn () => null,
        ));

        $builder->apply('tl_flare_filter', CalendarCurrentFilterElement::TYPE);
    }
}
