<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Filter\Element\DcaSelectFieldFilterElement;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Predicate\DcaSelectPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DcaSelectFieldFilterElementTest extends TestCase
{
    use BuildsFilterEffectsTrait;
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
        $effect = $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'status'],
            new ChoiceValue([new ValueChoice('published')]),
        );

        self::assertCount(1, $effect->formula->propositions);

        $p = $effect->formula->propositions[0];
        self::assertSame(DcaSelectPredicate::class, $p->predicateClass);
        self::assertSame('status', $p->options['field']);
        self::assertSame(['published'], $p->options['selected']);
        self::assertSame(['draft' => 'Draft', 'published' => 'Published'], $p->options['valid_options']);
        self::assertFalse($p->options['is_serialized']);
    }

    public function testMultipleDcaFieldIsPassedOn(): void
    {
        $effect = $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'tags'],
            new ChoiceValue([new ValueChoice('b'), new ValueChoice('a')]),
        );

        $p = $effect->formula->propositions[0];
        self::assertSame(['a', 'b'], $p->options['selected']);
        self::assertTrue($p->options['is_serialized']);
    }

    public function testFixedValueAppliesInValidation(): void
    {
        $effect = $this->buildFilterEffect(
            $this->createElement(),
            ['field' => 'status'],
            new ChoiceValue([new ValueChoice('draft')]),
            new ValidationIntent(),
        );

        self::assertCount(1, $effect->formula->propositions);
    }

    public function testNoValueAddsNothing(): void
    {
        $effect = $this->buildFilterEffect($this->createElement(), ['field' => 'status']);

        self::assertSame([], $effect->formula->propositions);
    }

    public function testValueForAFieldWithoutOptionsAborts(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildFilterEffect($this->createElement(), ['field' => 'free'], new ChoiceValue([new ValueChoice('a')]));
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

        $this->createElement()->buildChoices($choices, $this->createContext($field));

        return $choices->build();
    }

    private function createContext(string $field): ElementContext
    {
        $driver = $this->createMock(ListDriverInterface::class);
        $element = $this->createElement();

        return new ElementContext(
            list: new ListCompound(driver: $driver, type: 'test_list', dc: 'tl_test'),
            filter: new FilterCompound(
                element: new ElementBinding($element, 'test_element', new ComponentConfig(['field' => $field]), null),
                alias: 'test',
            ),
            intent: new ConfigurationIntent(),
        );
    }
}
