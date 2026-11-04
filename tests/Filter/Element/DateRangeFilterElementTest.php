<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\DateRangeFilterElement;
use HeimrichHannot\FlareBundle\Filter\Predicate\DateRangePredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use PHPUnit\Framework\TestCase;

final class DateRangeFilterElementTest extends TestCase
{
    use BuildsFilterEffectsTrait;

    public function testRangeBecomesTheDateRangePredicate(): void
    {
        $value = DateRangeValue::tryFrom(1000, 2000);

        $p = $this->buildFilterEffect(new DateRangeFilterElement(), ['field' => 'date'], $value)
            ->formula->propositions[0];

        self::assertSame(DateRangePredicate::class, $p->predicateClass);
        self::assertSame('date', $p->options['field']);
        self::assertSame(1000, $p->options['from']->getTimestamp());
        self::assertSame(2000, $p->options['to']->getTimestamp());
    }

    public function testOpenEndedRangeKeepsTheOtherBoundNull(): void
    {
        $p = $this->buildFilterEffect(
            new DateRangeFilterElement(),
            ['field' => 'date'],
            DateRangeValue::tryFrom(1000, null),
        )->formula->propositions[0];

        self::assertNull($p->options['to']);
    }

    public function testNoValueAddsNothing(): void
    {
        $effect = $this->buildFilterEffect(new DateRangeFilterElement(), ['field' => 'date']);

        self::assertSame([], $effect->formula->propositions);
    }

    public function testWrongValueClassIsRejected(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterEffect(new DateRangeFilterElement(), ['field' => 'date'], new BoolValue(true));
    }
}
