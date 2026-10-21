<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Integration\CodefogTags\FilterElement;

use HeimrichHannot\FlareBundle\Filter\Predicate\IntegerIdChoicePredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsChoiceFilterElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry\CfgTagsJoinsRegistry;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class CodefogTagsChoiceFilterElementTest extends TestCase
{
    use BuildsFilterContextsTrait;

    public function testSelectedTagsBecomeIntegerIds(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            [],
            new ChoiceValue([new ValueChoice('12', 'Foo [12]'), new ValueChoice('3', 'Bar [3]')]),
        );

        self::assertCount(1, $context->formula->propositions);

        $p = $context->formula->propositions[0];
        self::assertSame(IntegerIdChoicePredicate::class, $p->predicateClass);
        self::assertSame(['field' => 'id', 'ids' => [12, 3]], $p->options);
    }

    public function testNoValueAddsNothing(): void
    {
        self::assertSame([], $this->buildFilterContext($this->createElement())->formula->propositions);
    }

    public function testChoiceFromKeyAcceptsDigitStringsOnly(): void
    {
        $element = $this->createElement();

        $choice = $element->choiceFromKey('42');
        self::assertInstanceOf(ValueChoice::class, $choice);
        self::assertSame('42', $choice->value);

        self::assertNull($element->choiceFromKey('tl_cfg_tag.42'));
        self::assertNull($element->choiceFromKey('-1'));
        self::assertNull($element->choiceFromKey('foo'));
    }

    private function createElement(): CodefogTagsChoiceFilterElement
    {
        return new CodefogTagsChoiceFilterElement(
            new CfgTagsJoinsRegistry(),
            new ListExecutionContextFactory(new EventDispatcher()),
            new NullLogger(),
        );
    }
}
