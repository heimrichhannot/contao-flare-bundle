<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\ChoiceFilterControl;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceObjectInterface;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\ListModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ChoiceFilterControlTest extends TestCase
{
    use BuildsControlScopesTrait;

    private function control(): ChoiceFilterControl
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ChoiceFilterControl(new ChoicesBuilderFactory($translator, new ParameterBag()));
    }

    private function element(int ...$ids): FilterElementInterface&ChoiceSourceContract
    {
        return new class($ids) implements FilterElementInterface, ChoiceSourceContract {
            public function __construct(private array $ids) {}

            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void {}

            public function buildChoices(ChoicesBuilder $choices, ElementScope $scope): void
            {
                foreach ($this->ids as $id) {
                    $choices->add(ContaoModelChoice::ref('tl_news_archive', $id));
                }
            }

            public function choiceFromKey(string $key): ?ChoiceObjectInterface
            {
                return null;
            }
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function form(array $config, mixed $data, int ...$ids): FormInterface
    {
        $ctl = $this->control();
        $builder = $this->newFormBuilder();
        $ctl->buildForm($builder, $this->scope($ctl, $config, $this->element(...($ids ?: [5, 7]))));

        $single = $builder->getSingle();
        $single['options']['data'] = $data;

        return Forms::createFormFactory()->createNamed('test', $single['type'], null, $single['options']);
    }

    public function testHydratedCanonicalReferenceMatchesShortSubmittedKey(): void
    {
        $ctl = $this->control();
        $scope = $this->scope($ctl, [], $this->element(5, 7));

        $data = $ctl->hydrate(new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 5)]), $scope);
        $form = $this->form([], $data);

        self::assertSame('5', $form->createView()->vars['value']);

        $form->submit('7');
        self::assertSame('7', $ctl->decode($form->getData(), $scope)->choices[0]->getChoiceLocalKey());
    }

    public function testMultipleHydratesAllChoices(): void
    {
        $ctl = $this->control();
        $scope = $this->scope($ctl, ['is_multiple' => true], $this->element(5, 7));

        $value = new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 5), ContaoModelChoice::ref('tl_news_archive', 7)]);

        self::assertSame($value->choices, $ctl->hydrate($value, $scope));
        self::assertSame([], $ctl->hydrate(null, $scope));

        $form = $this->form(['is_multiple' => true], $ctl->hydrate($value, $scope));
        self::assertSame(['5', '7'], $form->createView()->vars['value']);
    }

    public function testSingleSelectHydratesTheFirstChoice(): void
    {
        $ctl = $this->control();
        $scope = $this->scope($ctl, [], $this->element(5, 7));
        $value = new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 7), ContaoModelChoice::ref('tl_news_archive', 5)]);

        self::assertSame($value->choices[0], $ctl->hydrate($value, $scope));
        self::assertNull($ctl->hydrate(null, $scope));
    }

    public function testDecode(): void
    {
        $ctl = $this->control();
        $scope = $this->scope($ctl, [], $this->element(5));
        $choice = ContaoModelChoice::ref('tl_news_archive', 5);

        self::assertEquals(new ChoiceValue([$choice]), $ctl->decode($choice, $scope));
        self::assertEquals(new ChoiceValue([$choice]), $ctl->decode([$choice, ChoicesBuilder::EMPTY_CHOICE], $scope));
        self::assertNull($ctl->decode(null, $scope));
        self::assertNull($ctl->decode([], $scope));
        self::assertNull($ctl->decode(ChoicesBuilder::EMPTY_CHOICE, $scope));
    }

    public function testElementWithoutChoiceSourceFails(): void
    {
        $ctl = $this->control();

        $this->expectException(FilterException::class);

        $ctl->buildForm($this->newFormBuilder(), $this->scope($ctl));
    }

    public function testPlaceholderAttributeOnlyWithoutEmptyOption(): void
    {
        $ctl = $this->control();

        $without = $this->newFormBuilder();
        $ctl->buildForm($without, $this->scope($ctl, ['placeholder' => 'Pick'], $this->element(5)));
        self::assertSame(['placeholder' => 'Pick'], $without->getSingle()['options']['attr']);

        $with = $this->newFormBuilder();
        $ctl->buildForm($with, $this->scope($ctl, ['placeholder' => 'Pick', 'has_empty_option' => true], $this->element(5)));
        self::assertArrayNotHasKey('attr', $with->getSingle()['options']);
    }

    public function testNoImplicitSymfonyPlaceholderWithoutTheEmptyOption(): void
    {
        $radios = $this->form(['is_expanded' => true], null, 5, 7)->createView();
        self::assertCount(2, $radios->children);
        self::assertNull($radios->vars['placeholder']);

        $select = $this->form([], null, 5, 7)->createView();
        self::assertNull($select->vars['placeholder']);
    }

    public function testFormOptionsAndNoChoicesBuilderAttribute(): void
    {
        $ctl = $this->control();
        $builder = $this->newFormBuilder();

        $ctl->buildForm($builder, $this->scope($ctl, [
            'label' => 'Archive',
            'is_mandatory' => true,
            'is_multiple' => true,
            'is_expanded' => true,
        ], $this->element(5)));

        $single = $builder->getSingle();
        self::assertSame(ChoiceType::class, $single['type']);
        self::assertSame('Archive', $single['options']['label']);
        self::assertTrue($single['options']['required']);
        self::assertTrue($single['options']['multiple']);
        self::assertTrue($single['options']['expanded']);
        self::assertArrayHasKey('choice_loader', $single['options']);
        self::assertFalse($builder->hasAttribute('flare.choices_builder'));
    }

    public function testEmptyOptionValueIsAlternativeWhenExpandedAndMultiple(): void
    {
        $ctl = $this->control();

        $builder = $this->newFormBuilder();
        $ctl->buildForm($builder, $this->scope($ctl, [
            'has_empty_option' => true,
            'is_multiple' => true,
            'is_expanded' => true,
        ], $this->element(5)));

        $valueOf = $builder->getSingle()['options']['choice_value'];
        self::assertSame(ChoicesBuilder::EMPTY_CHOICE_VALUE_ALTERNATIVE, $valueOf(ChoicesBuilder::EMPTY_CHOICE));

        $builder = $this->newFormBuilder();
        $ctl->buildForm($builder, $this->scope($ctl, ['has_empty_option' => true], $this->element(5)));

        $valueOf = $builder->getSingle()['options']['choice_value'];
        self::assertSame(ChoicesBuilder::EMPTY_CHOICE_VALUE_DEFAULT, $valueOf(ChoicesBuilder::EMPTY_CHOICE));
    }

    public function testTransformerResolvesCustomFormats(): void
    {
        $ctl = $this->control();

        $config = $this->transform($ctl, [
            'ctl_label' => 'L',
            'ctl_formatLabel' => 'custom',
            'ctl_formatLabelCustom' => '%title%',
            'ctl_isMandatory' => '1',
            'ctl_isMultiple' => '1',
            'ctl_isExpanded' => '',
            'ctl_hasEmptyOption' => '1',
            'ctl_formatEmptyOption' => 'custom',
            'ctl_formatEmptyOptionCustom' => 'Any',
            'ctl_placeholder' => 'P',
        ]);

        self::assertSame([
            'label' => 'L',
            'format_label' => '%title%',
            'is_mandatory' => true,
            'is_multiple' => true,
            'is_expanded' => false,
            'has_empty_option' => true,
            'format_empty_option' => 'Any',
            'placeholder' => 'P',
        ], $config);

        $plain = $this->transform($ctl, [
            'ctl_formatLabel' => 'format.title',
            'ctl_formatEmptyOption' => 'empty_option.all',
        ]);
        self::assertSame('format.title', $plain['format_label']);
        self::assertSame('empty_option.all', $plain['format_empty_option']);
        self::assertNull($plain['label']);
    }

    public function testPaletteOffersTheEmptyOptionLabel(): void
    {
        $dca = $this->createMock(DcaBuilderInterface::class);
        $dca->expects(self::once())->method('palette')
            ->with(self::stringContains('ctl_hasEmptyOption,ctl_formatEmptyOption,ctl_placeholder'));

        ($this->control())->buildDca($dca, new DcaContext(
            'tl_flare_filter',
            'test',
            (new \ReflectionClass(ListModel::class))->newInstanceWithoutConstructor(),
            null,
            static fn () => null,
        ));
    }
}
