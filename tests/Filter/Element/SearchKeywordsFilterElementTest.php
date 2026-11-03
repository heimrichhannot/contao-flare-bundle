<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\SearchKeywordsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Predicate\SearchKeywordsPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;

final class SearchKeywordsFilterElementTest extends TestCase
{
    use BuildsFilterContextsTrait;

    public function testKeywordsAndColumnsBecomeThePredicate(): void
    {
        $context = $this->buildFilterContext(
            new SearchKeywordsFilterElement(),
            ['columns' => ['title', 'teaser']],
            new KeywordsValue('foo  bar'),
        );

        $p = $context->formula->propositions[0];

        self::assertSame(SearchKeywordsPredicate::class, $p->predicateClass);
        self::assertSame('foo bar', $p->options['value']);
        self::assertSame(['title', 'teaser'], $p->options['columns']);
    }

    public function testNoValueAddsNothing(): void
    {
        $element = new SearchKeywordsFilterElement();

        self::assertSame([], $this->buildFilterContext($element, ['columns' => ['title']])->formula->propositions);
    }

    public function testWrongValueClassIsRejected(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterContext(new SearchKeywordsFilterElement(), ['columns' => ['title']], new BoolValue(true));
    }
}
