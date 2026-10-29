<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\BooleanFilterElement;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;

final class BooleanFilterElementTest extends TestCase
{
    use BuildsFilterContextsTrait;

    public function testWithoutValueAddsNothingAndWithValueMatchesIt(): void
    {
        $bool = new BooleanFilterElement();

        self::assertSame([], $this->buildFilterContext($bool, ['field' => 'featured'])->formula->propositions);

        $p = $this->buildFilterContext($bool, ['field' => 'featured'], new BoolValue(false))
            ->formula->propositions[0];

        self::assertSame([BooleanPredicate::class, 'featured', false], [$p->predicateClass, $p->options['field'], $p->options['value']]);

        $p = $this->buildFilterContext($bool, ['field' => 'featured'], new BoolValue(true))->formula->propositions[0];

        self::assertTrue($p->options['value']);
    }

    public function testWrongValueClassIsRejected(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterContext(
            new BooleanFilterElement(),
            ['field' => 'featured'],
            DateRangeValue::tryFrom(1, 2),
        );
    }
}
