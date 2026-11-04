<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\FieldValueChoiceFilterElement;
use HeimrichHannot\FlareBundle\Filter\Predicate\FieldValueChoicePredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use PHPUnit\Framework\TestCase;

final class FieldValueChoiceFilterElementTest extends TestCase
{
    use BuildsFilterEffectsTrait;

    public function testSelectedValuesBecomeTheFieldValueChoicePredicate(): void
    {
        $effect = $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice('a'), new ValueChoice('b')]),
        );

        self::assertCount(1, $effect->formula->propositions);

        $p = $effect->formula->propositions[0];
        self::assertSame(FieldValueChoicePredicate::class, $p->predicateClass);
        self::assertSame('category', $p->options['field']);
        self::assertSame(['a', 'b'], $p->options['values']);
    }

    public function testDuplicateLabelsResolveToDistinctValues(): void
    {
        $effect = $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'author'],
            new ChoiceValue([new ValueChoice('1', 'Same'), new ValueChoice('2', 'Same')]),
        );

        self::assertSame(['1', '2'], $effect->formula->propositions[0]->options['values']);
    }

    public function testValuesAreNormalizedForTheCaseInsensitiveComparison(): void
    {
        $effect = $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice(' Äpfel '), new ValueChoice('äpfel')]),
        );

        self::assertSame(['äpfel'], $effect->formula->propositions[0]->options['values']);
    }

    public function testFixedValueAppliesInValidation(): void
    {
        $effect = $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice('a')]),
            new ValidationIntent(),
        );

        self::assertCount(1, $effect->formula->propositions);
    }

    public function testNoValueAddsNothing(): void
    {
        $effect = $this->buildFilterEffect($this->createElement(), ['field' => 'category']);

        self::assertSame([], $effect->formula->propositions);
    }

    public function testBlankValuesAreDropped(): void
    {
        $effect = $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice('  '), new ValueChoice('a')]),
        );

        self::assertSame(['a'], $effect->formula->propositions[0]->options['values']);
    }

    public function testOnlyBlankValuesAbort(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'category'],
            new ChoiceValue([new ValueChoice(''), new ValueChoice(' ')]),
        );
    }

    public function testValueOfAnotherClassFails(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterEffect($this->createElement(), ['field' => 'category'], new BoolValue(true));
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
