<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\FieldValueChoiceFilterElement;
use HeimrichHannot\FlareBundle\Filter\Predicate\FieldValueChoicePredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;

final class FieldValueChoiceFilterElementTest extends TestCase
{
    use BuildsFilterContextsTrait;

    public function testSelectedValuesBecomeTheFieldValueChoicePredicate(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice('a'), new ValueChoice('b')]),
        );

        self::assertCount(1, $context->formula->propositions);

        $p = $context->formula->propositions[0];
        self::assertSame(FieldValueChoicePredicate::class, $p->predicateClass);
        self::assertSame('category', $p->options['field']);
        self::assertSame(['a', 'b'], $p->options['values']);
    }

    public function testDuplicateLabelsResolveToDistinctValues(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'author'],
            new ChoiceValue([new ValueChoice('1', 'Same'), new ValueChoice('2', 'Same')]),
        );

        self::assertSame(['1', '2'], $context->formula->propositions[0]->options['values']);
    }

    public function testValuesAreNormalizedForTheCaseInsensitiveComparison(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice(' Äpfel '), new ValueChoice('äpfel')]),
        );

        self::assertSame(['äpfel'], $context->formula->propositions[0]->options['values']);
    }

    public function testFixedValueAppliesInValidation(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice('a')]),
            new ValidationContext(),
        );

        self::assertCount(1, $context->formula->propositions);
    }

    public function testNoValueAddsNothing(): void
    {
        $context = $this->buildFilterContext($this->createElement(), ['field' => 'category']);

        self::assertSame([], $context->formula->propositions);
    }

    public function testNoValueAndNoFieldAddsNothing(): void
    {
        $context = $this->buildFilterContext($this->createElement(), ['field' => null]);

        self::assertSame([], $context->formula->propositions);
    }

    public function testValueWithoutFieldAborts(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildFilterContext($this->createElement(), ['field' => null], new ChoiceValue([new ValueChoice('a')]));
    }

    public function testBlankValuesAreDropped(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice('  '), new ValueChoice('a')]),
        );

        self::assertSame(['a'], $context->formula->propositions[0]->options['values']);
    }

    public function testOnlyBlankValuesAbort(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice(''), new ValueChoice(' ')]),
        );
    }

    public function testValueOfAnotherClassFails(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterContext($this->createElement(), ['field' => 'category'], new BoolValue(true));
    }

    public function testChoiceFromKeyKeepsDotsInTheValue(): void
    {
        $choice = $this->createElement()->choiceFromKey('1.5');

        self::assertInstanceOf(ValueChoice::class, $choice);
        self::assertSame('1.5', $choice->value);
    }

    private function createElement(): FieldValueChoiceFilterElement
    {
        return new FieldValueChoiceFilterElement($this->createMock(Connection::class));
    }
}
