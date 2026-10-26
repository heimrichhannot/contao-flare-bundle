<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\CalendarCurrentFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Predicate\CalendarCurrentPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use PHPUnit\Framework\TestCase;

final class CalendarCurrentFilterElementTest extends TestCase
{
    use BuildsFilterContextsTrait;

    private const BASE = [
        'is_limited' => false,
        'configure_start' => null,
        'configure_stop' => null,
        'start_at' => null,
        'stop_at' => null,
        'has_extended_events' => false,
    ];

    public function testValueNarrowsTheUnlimitedRange(): void
    {
        $p = $this->buildFilterContext(
            new CalendarCurrentFilterElement(),
            self::BASE,
            DateRangeValue::tryFrom(1000, 2000),
        )->formula->propositions[0];

        self::assertSame(CalendarCurrentPredicate::class, $p->predicateClass);
        self::assertSame([1000, 2000], [$p->options['start'], $p->options['stop']]);
    }

    public function testLimitedRangeClampsTheValue(): void
    {
        $config = ['is_limited' => true, 'start_at' => '@1000', 'stop_at' => '@2000'] + self::BASE;

        $p = $this->buildFilterContext(
            new CalendarCurrentFilterElement(),
            $config,
            DateRangeValue::tryFrom(500, 3000),
        )->formula->propositions[0];

        self::assertSame([1000, 2000], [$p->options['start'], $p->options['stop']]);
    }

    public function testValidationSkipsAnUnlimitedFilter(): void
    {
        $context = $this->buildFilterContext(
            new CalendarCurrentFilterElement(),
            self::BASE,
            null,
            new ValidationContext(),
        );

        self::assertSame([], $context->formula->propositions);
    }

    public function testValidationAppliesAFixedRangeWithoutTheConfiguredDefaults(): void
    {
        $config = ['start_at' => '@5000', 'stop_at' => '@6000'] + self::BASE;

        $p = $this->buildFilterContext(
            new CalendarCurrentFilterElement(),
            $config,
            DateRangeValue::tryFrom(1000, null),
            new ValidationContext(),
        )->formula->propositions[0];

        self::assertSame(CalendarCurrentPredicate::class, $p->predicateClass);
        self::assertSame([1000, DateTimeHelper::maxTimestamp()], [$p->options['start'], $p->options['stop']]);
    }

    public function testCalendarBoundsOnlyWhenLimited(): void
    {
        $element = new CalendarCurrentFilterElement();

        $open = $element->getDateBounds($this->createScope($element, self::BASE));
        self::assertNull($open->min);
        self::assertNull($open->max);

        $bounds = $element->getDateBounds($this->createScope($element, [
            'is_limited' => true,
            'configure_start' => '1',
            'configure_stop' => '1',
            'start_at' => '@1000',
            'stop_at' => '@2000',
        ] + self::BASE));

        self::assertSame([1000, 2000], [$bounds->min?->getTimestamp(), $bounds->max?->getTimestamp()]);
    }

    public function testWrongValueClassIsRejected(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterContext(new CalendarCurrentFilterElement(), self::BASE, new BoolValue(true));
    }

    private function createScope(CalendarCurrentFilterElement $element, array $config): FilterScope
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        return new FilterScope(
            list: new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test'),
            filter: new Filter(element: $element, type: 'test_element', alias: 'test', config: $config),
            engineContext: new ConfigurationContext(),
        );
    }
}
