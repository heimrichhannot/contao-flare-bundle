<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Filter\Element\DcaSelectFieldFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Predicate\DcaSelectPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DcaSelectFieldFilterElementTest extends TestCase
{
    use BuildsFilterContextsTrait;
    use StubsContaoDcaTrait;

    protected function setUp(): void
    {
        $this->stubDca('tl_test', [
            'fields' => [
                'status' => [
                    'inputType' => 'select',
                    'options' => ['draft', 'published'],
                    'reference' => ['draft' => 'Draft', 'published' => 'Published'],
                ],
                'tags' => [
                    'inputType' => 'select',
                    'options' => ['a' => 'Alpha', 'b' => 'Beta'],
                    'eval' => ['multiple' => true],
                ],
                'free' => ['inputType' => 'select'],
                'blank' => [
                    'inputType' => 'select',
                    'options' => ['' => '-', 'x' => 'Ex'],
                ],
            ],
        ]);
    }

    public function testSelectedValuesBecomeTheDcaSelectPredicate(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'status'],
            new ChoiceValue([new ValueChoice('published')]),
        );

        self::assertCount(1, $context->formula->propositions);

        $p = $context->formula->propositions[0];
        self::assertSame(DcaSelectPredicate::class, $p->predicateClass);
        self::assertSame('status', $p->options['field']);
        self::assertSame(['published'], $p->options['selected']);
        self::assertSame(['draft' => 'Draft', 'published' => 'Published'], $p->options['valid_options']);
        self::assertFalse($p->options['is_serialized']);
    }

    public function testMultipleDcaFieldIsPassedOn(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'tags'],
            new ChoiceValue([new ValueChoice('b'), new ValueChoice('a')]),
        );

        $p = $context->formula->propositions[0];
        self::assertSame(['a', 'b'], $p->options['selected']);
        self::assertTrue($p->options['is_serialized']);
    }

    public function testFixedValueAppliesInValidation(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['field' => 'status'],
            new ChoiceValue([new ValueChoice('draft')]),
            new ValidationContext(),
        );

        self::assertCount(1, $context->formula->propositions);
    }

    public function testNoValueAddsNothing(): void
    {
        $context = $this->buildFilterContext($this->createElement(), ['field' => 'status']);

        self::assertSame([], $context->formula->propositions);
    }

    public function testValueWithoutFieldAborts(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildFilterContext($this->createElement(), ['field' => null], new ChoiceValue([new ValueChoice('a')]));
    }

    public function testValueForAFieldWithoutOptionsAborts(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildFilterContext($this->createElement(), ['field' => 'free'], new ChoiceValue([new ValueChoice('a')]));
    }

    public function testBuildChoicesKeysTheFieldOptionsByValue(): void
    {
        $choices = $this->buildChoices('status');

        self::assertEquals(
            ['draft' => new ValueChoice('draft', 'Draft'), 'published' => new ValueChoice('published', 'Published')],
            $choices,
        );
    }

    public function testBuildChoicesSkipsTheBlankOption(): void
    {
        $choices = $this->buildChoices('blank');

        self::assertEquals(['x' => new ValueChoice('x', 'Ex')], $choices);
    }

    public function testChoiceFromKeyIsAValueChoice(): void
    {
        $choice = $this->createElement()->choiceFromKey('published');

        self::assertInstanceOf(ValueChoice::class, $choice);
        self::assertSame('published', $choice->value);
    }

    private function createElement(): DcaSelectFieldFilterElement
    {
        return new DcaSelectFieldFilterElement();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChoices(string $field): array
    {
        $choices = (new ChoicesBuilderFactory(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(ParameterBagInterface::class),
        ))->createChoicesBuilder();

        $this->createElement()->buildChoices($choices, $this->createScope($field));

        return $choices->build();
    }

    private function createScope(string $field): FilterScope
    {
        $driver = $this->createMock(ListDriverInterface::class);
        $element = $this->createElement();

        return new FilterScope(
            list: new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test'),
            filter: new Filter(element: $element, type: 'test_element', alias: 'test', config: ['field' => $field]),
            engineContext: new ConfigurationContext(),
        );
    }
}
