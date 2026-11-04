<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\BooleanFilterElement;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use PHPUnit\Framework\TestCase;

final class BooleanFilterElementTest extends TestCase
{
    use BuildsFilterEffectsTrait;

    public function testWithoutValueAddsNothingAndWithValueMatchesIt(): void
    {
        $bool = new BooleanFilterElement();

        self::assertSame([], $this->buildFilterEffect($bool, ['field' => 'featured'])->formula->propositions);

        $p = $this->buildFilterEffect($bool, ['field' => 'featured'], new BoolValue(false))
            ->formula->propositions[0];

        self::assertSame([BooleanPredicate::class, 'featured', false], [$p->predicateClass, $p->options['field'], $p->options['value']]);

        $p = $this->buildFilterEffect($bool, ['field' => 'featured'], new BoolValue(true))->formula->propositions[0];

        self::assertTrue($p->options['value']);
    }

    public function testWrongValueClassIsRejected(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterEffect(
            new BooleanFilterElement(),
            ['field' => 'featured'],
            DateRangeValue::tryFrom(1, 2),
        );
    }
}
