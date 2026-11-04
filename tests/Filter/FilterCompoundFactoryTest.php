<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\ValueClassMismatchError;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterCompoundFactory;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Tests\Fixture\Filter\FactoryStubBoolControl;
use HeimrichHannot\FlareBundle\Tests\Fixture\Filter\FactoryStubBoolElement;
use HeimrichHannot\FlareBundle\Tests\Fixture\Filter\FactoryStubKeywordsControl;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterCompoundFactoryTest extends TestCase
{
    private const BOOL_ELEMENT = 'bool_element';
    private const BOOL_CONTROL = 'bool_control';
    private const KEYWORDS_CONTROL = 'keywords_control';
    private const SOURCED_ELEMENT = 'sourced_element';
    private const SOURCED_CONTROL = 'sourced_control';

    private FilterElementInterface $boolElement;
    private FilterControlInterface $boolControl;

    protected function setUp(): void
    {
        $this->boolElement = new FactoryStubBoolElement();
        $this->boolControl = new FactoryStubBoolControl();
    }

    private function factory(?FilterElementRegistry $elements = null): FilterCompoundFactory
    {
        $elements ??= (new FilterElementRegistry())
            ->add(
                $this->boolElement,
                new AsFilterElement(type: self::BOOL_ELEMENT, value: BoolValue::class),
                self::BOOL_ELEMENT,
            )
            ->add(
                new FactoryStubSourcedElement(),
                new AsFilterElement(type: self::SOURCED_ELEMENT, value: BoolValue::class),
                self::SOURCED_ELEMENT,
            );

        $controls = (new FilterControlRegistry())
            ->add($this->boolControl, self::BOOL_CONTROL, BoolValue::class)
            ->add(new FactoryStubKeywordsControl(), self::KEYWORDS_CONTROL, KeywordsValue::class)
            ->add(new FactoryStubSourcedControl(), self::SOURCED_CONTROL, BoolValue::class);

        return new FilterCompoundFactory(
            filterElementRegistry: $elements,
            filterControlRegistry: $controls,
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher()),
        );
    }

    public function testCreatesFromRegisteredTypeAlias(): void
    {
        $element = new FactoryStubPlainElement();

        $registry = new FilterElementRegistry();
        $registry->add($element, null, 'my_element');

        $filter = $this->factory($registry)->create(
            element: 'my_element',
            alias: 'foo',
            config: ['a' => 1],
        );

        self::assertSame($element, $filter->element->component);
        self::assertSame('my_element', $filter->element->type);
        self::assertSame(['a' => 1], $filter->element->config->all());
        self::assertSame('foo', $filter->alias);
        self::assertNull($filter->control);
        self::assertNull($filter->value);
    }

    public function testCreatesFromInstanceUsingItsClassNameAsType(): void
    {
        $element = new FactoryStubPlainElement();

        $filter = $this->factory()->create(element: $element);

        self::assertSame($element, $filter->element->component);
        self::assertSame(FactoryStubPlainElement::class, $filter->element->type);
        self::assertStringStartsWith('_.' . FactoryStubPlainElement::class . '_', $filter->alias);
    }

    public function testCreateResolvesSchemaDefaultsIntoComponentConfig(): void
    {
        $filter = $this->factory()->create(element: new FactoryStubOptionsElement(), config: []);

        self::assertSame([], $filter->element->config->get('columns'));
    }

    public function testCreateDefaultsControlTypeToClassNameForInlineControl(): void
    {
        $control = new FactoryStubBoolControl();

        $filter = $this->factory()->create(element: self::BOOL_ELEMENT, alias: 'foo', control: $control);

        self::assertSame(FactoryStubBoolControl::class, $filter->control?->type);
    }

    public function testThrowsForUnknownTypeAlias(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('Filter element type "missing" not found');

        $this->factory()->create(element: 'missing');
    }

    public function testCreatesWithControlByNameAndResolvesItsConfig(): void
    {
        $filter = $this->factory()->create(
            element: self::BOOL_ELEMENT,
            alias: 'foo',
            control: self::BOOL_CONTROL,
            value: new BoolValue(true),
        );

        self::assertSame($this->boolControl, $filter->control?->component);
        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
        self::assertSame(['label' => 'default'], $filter->control?->config->all());
        self::assertEquals(new BoolValue(true), $filter->value);
    }

    public function testControlledFilterWithoutAliasIsRejected(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('needs an alias');

        $this->factory()->create(element: self::BOOL_ELEMENT, control: self::BOOL_CONTROL);
    }

    public function testControlledFilterWithAnInvalidFormNameIsRejected(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('"price.min"');

        $this->factory()->create(element: self::BOOL_ELEMENT, alias: 'price.min', control: self::BOOL_CONTROL);
    }

    public function testFilterWithoutControlKeepsAnInvalidAlias(): void
    {
        $filter = $this->factory()->create(element: self::BOOL_ELEMENT, alias: 'price.min');

        self::assertSame('price.min', $filter->alias);
    }

    public function testUnknownControlNameIsRejected(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->create(element: self::BOOL_ELEMENT, alias: 'foo', control: 'gone');
    }

    public function testProgrammaticControlOfTheWrongClassIsRejected(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->create(
            element: self::BOOL_ELEMENT,
            alias: 'foo',
            control: new FactoryStubKeywordsControl(),
        );
    }

    public function testProgrammaticValueOfTheWrongClassIsRejected(): void
    {
        $this->expectException(ValueClassMismatchError::class);

        $this->factory()->create(element: self::BOOL_ELEMENT, value: new KeywordsValue('foo'));
    }

    public function testValueClassIsReadFromTheAttributeOfAnElementInstance(): void
    {
        $factory = $this->factory();

        $filter = $factory->create(element: new FactoryStubBoolElement(), value: new BoolValue(false));

        self::assertEquals(new BoolValue(false), $filter->value);

        $this->expectException(ValueClassMismatchError::class);

        $factory->create(element: new FactoryStubBoolElement(), value: new KeywordsValue('foo'));
    }

    public function testElementWithoutValueClassRejectsAnyValue(): void
    {
        $this->expectException(ValueClassMismatchError::class);

        $this->factory()->create(element: new FactoryStubPlainElement(), value: new BoolValue(true));
    }

    public function testConfigSourceIsTransformedForElementAndControl(): void
    {
        $filter = $this->factory()->create(
            element: self::SOURCED_ELEMENT,
            alias: 'a',
            control: self::SOURCED_CONTROL,
            configSource: new \ArrayObject(['label' => 'L', 'placeholder' => 'P']),
        );

        self::assertSame('L', $filter->element->config->get('label'));
        self::assertSame('P', $filter->control?->config->get('placeholder'));
    }

    public function testExplicitConfigWinsOverTheConfigSource(): void
    {
        $filter = $this->factory()->create(
            element: self::SOURCED_ELEMENT,
            alias: 'a',
            config: ['label' => 'X'],
            control: self::SOURCED_CONTROL,
            configSource: new \ArrayObject(['label' => 'L', 'placeholder' => 'P']),
        );

        self::assertSame('X', $filter->element->config->get('label'));
        self::assertSame('P', $filter->control?->config->get('placeholder'));
    }

    public function testExplicitElementConfigKeepsTheOtherSourceKeys(): void
    {
        $filter = $this->factory()->create(
            element: self::SOURCED_ELEMENT,
            config: ['label' => 'X'],
            configSource: new \ArrayObject(['label' => 'L', 'hint' => 'H']),
        );

        self::assertSame('X', $filter->element->config->get('label'));
        self::assertSame('H', $filter->element->config->get('hint'));
    }

    public function testWithoutConfigSourceNoTransformerRuns(): void
    {
        self::assertSame('', $this->factory()->create(element: self::SOURCED_ELEMENT)->element->config->get('label'));
    }

    public function testUnmatchedConfigSourceLeavesTheConfigAlone(): void
    {
        $filter = $this->factory()->create(element: self::SOURCED_ELEMENT, configSource: new \stdClass());

        self::assertSame('', $filter->element->config->get('label'));
    }

    public function testCreateRecordsTheValueClassOnTheBinding(): void
    {
        self::assertSame(BoolValue::class, $this->factory()->create(element: self::BOOL_ELEMENT)->element->valueClass);
        self::assertNull($this->factory()->create(element: new FactoryStubPlainElement())->element->valueClass);
    }

    public function testZeroIsAValidAlias(): void
    {
        self::assertSame('0', $this->factory()->create(element: self::BOOL_ELEMENT, alias: '0')->alias);
        self::assertSame(
            '0',
            $this->factory()->create(element: self::BOOL_ELEMENT, alias: '0', control: self::BOOL_CONTROL)->alias,
        );
    }
}

final class FactoryStubPlainElement implements FilterElementInterface
{
    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {}
}

final class FactoryStubOptionsElement implements FilterElementInterface, OptionsContract
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('columns')->default([])->allowedTypes('array');
    }

    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {}
}

final class FactoryStubSourcedElement implements FilterElementInterface, OptionsContract, TransformerContract
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default('')->allowedTypes('string');
        $resolver->define('hint')->default('')->allowedTypes('string');
    }

    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->set(
            sourceClass: \ArrayObject::class,
            transformer: static function (ConfigBuilder $config, \ArrayObject $source): void {
                $config->set('label', $source['label'] ?? '');
                $config->set('hint', $source['hint'] ?? '');
            },
        );
    }

    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {}
}

final class FactoryStubSourcedControl extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('placeholder')->default('')->allowedTypes('string');
    }

    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->set(
            sourceClass: \ArrayObject::class,
            transformer: static function (ConfigBuilder $config, \ArrayObject $source): void {
                $config->set('placeholder', $source['placeholder']);
            },
        );
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, ControlContext $context): void {}

    public function decode(mixed $data, ControlContext $context): ?BoolValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlContext $context): mixed
    {
        return null;
    }
}
