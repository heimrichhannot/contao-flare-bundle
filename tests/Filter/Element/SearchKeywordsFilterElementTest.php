<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\SearchKeywordsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Predicate\SearchKeywordsPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use PHPUnit\Framework\TestCase;

final class SearchKeywordsFilterElementTest extends TestCase
{
    use BuildsFilterEffectsTrait;

    public function testKeywordsAndColumnsBecomeThePredicate(): void
    {
        $effect = $this->buildFilterEffect(
            new SearchKeywordsFilterElement(),
            ['columns' => ['title', 'teaser']],
            new KeywordsValue('foo  bar'),
        );

        $p = $effect->formula->propositions[0];

        self::assertSame(SearchKeywordsPredicate::class, $p->predicateClass);
        self::assertSame('foo bar', $p->options['value']);
        self::assertSame(['title', 'teaser'], $p->options['columns']);
    }

    public function testNoValueAddsNothing(): void
    {
        $element = new SearchKeywordsFilterElement();

        self::assertSame([], $this->buildFilterEffect($element, ['columns' => ['title']])->formula->propositions);
    }

    public function testWrongValueClassIsRejected(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterEffect(new SearchKeywordsFilterElement(), ['columns' => ['title']], new BoolValue(true));
    }
}
