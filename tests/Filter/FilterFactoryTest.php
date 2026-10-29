<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterFactoryTest extends TestCase
{
    private const BOOL_ELEMENT = 'bool_element';
    private const BOOL_CONTROL = 'bool_control';
    private const KEYWORDS_CONTROL = 'keywords_control';

    /** @var list<array{string, string}> */
    private array $logs = [];

    private FilterElementInterface $boolElement;
    private FilterControlInterface $boolControl;

    protected function setUp(): void
    {
        $this->logs = [];
        $this->boolElement = new FactoryStubBoolElement();
        $this->boolControl = new FactoryStubBoolControl();
    }

    private function factory(?FilterElementRegistry $elements = null): FilterFactory
    {
        $elements ??= (new FilterElementRegistry())->add(
            $this->boolElement,
            new AsFilterElement(type: self::BOOL_ELEMENT, value: BoolValue::class),
            self::BOOL_ELEMENT,
        );

        $controls = (new FilterControlRegistry())
            ->add($this->boolControl, self::BOOL_CONTROL, BoolValue::class)
            ->add(new FactoryStubKeywordsControl(), self::KEYWORDS_CONTROL, KeywordsValue::class);

        $providers = (new ValueProviderRegistry())->add(new FactoryStubBoolProvider(), BoolValue::class);

        $logs = &$this->logs;
        $logger = new class ($logs) extends AbstractLogger {
            public function __construct(private array &$logs) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        return new FilterFactory(
            filterElementRegistry: $elements,
            filterControlRegistry: $controls,
            valueProviderRegistry: $providers,
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher()),
            logger: $logger,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function model(array $row): FilterModel
    {
        $model = (new \ReflectionClass(FilterModel::class))->newInstanceWithoutConstructor();

        (function (array $row): void {
            $this->arrData = $row;
        })->call($model, $row + ['id' => 7, 'type' => self::BOOL_ELEMENT, 'control' => '']);

        return $model;
    }

    private function assertWarned(): void
    {
        self::assertCount(1, $this->logs);
        self::assertSame('warning', $this->logs[0][0]);
        self::assertStringContainsString('tl_flare_filter.7', $this->logs[0][1]);
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

    public function testControlledFilterModelWithAnInvalidFormNameIsRejected(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('"price.min"');

        $this->factory()->createFromFilterModel(self::model(['alias' => 'price.min', 'control' => self::BOOL_CONTROL]));
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
        $this->expectException(FlareException::class);

        $this->factory()->create(element: self::BOOL_ELEMENT, value: new KeywordsValue('foo'));
    }

    public function testValueClassIsReadFromTheAttributeOfAnElementInstance(): void
    {
        $factory = $this->factory();

        $filter = $factory->create(element: new FactoryStubBoolElement(), value: new BoolValue(false));

        self::assertEquals(new BoolValue(false), $filter->value);

        $this->expectException(FlareException::class);

        $factory->create(element: new FactoryStubBoolElement(), value: new KeywordsValue('foo'));
    }

    public function testElementWithoutValueClassRejectsAnyValue(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->create(element: new FactoryStubPlainElement(), value: new BoolValue(true));
    }

    public function testValueComesFromTheProviderOfTheElementsValueClass(): void
    {
        $fixed = $this->factory()->createFromFilterModel(self::model(['value' => '1']));

        self::assertSame($this->boolElement, $fixed->element->component);
        self::assertSame(self::BOOL_ELEMENT, $fixed->element->type);
        self::assertSame('7', $fixed->alias);
        self::assertSame('tl_flare_filter.7', $fixed->source);
        self::assertNull($fixed->control);
        self::assertEquals(new BoolValue(true), $fixed->value);

        $initial = $this->factory()->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => '0']),
        );

        self::assertSame($this->boolControl, $initial->control?->component);
        self::assertSame(self::BOOL_CONTROL, $initial->control?->type);
        self::assertSame(['label' => 'from model'], $initial->control?->config->all());
        self::assertEquals(new BoolValue(false), $initial->value);
        self::assertSame([], $this->logs);
    }

    public function testMissingValueColumnYieldsNoValue(): void
    {
        $filter = $this->factory()->createFromFilterModel(self::model([]));

        self::assertNull($filter->value);
    }

    public function testUnknownControlThrows(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->createFromFilterModel(self::model(['control' => 'gone', 'value' => '1']));
    }

    public function testControlForAnotherValueClassThrows(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->createFromFilterModel(self::model(['control' => self::KEYWORDS_CONTROL, 'value' => '1']));
    }

    public function testUnreadableInitialValueKeepsTheControl(): void
    {
        $filter = $this->factory()->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => 'garbage']),
        );

        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
        self::assertNull($filter->value);
        $this->assertWarned();
    }

    public function testInitialValueFailingWithAPlainFilterExceptionKeepsTheControl(): void
    {
        $filter = $this->factory()->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => 'plain']),
        );

        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
        self::assertNull($filter->value);
        $this->assertWarned();
    }

    public function testProviderExceptionIsWrappedForAFixedValue(): void
    {
        try {
            $this->factory()->createFromFilterModel(self::model(['value' => 'bug']));
            self::fail('No exception thrown.');
        } catch (FilterException $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
            self::assertSame('tl_flare_filter.7', $e->getSource());
        }

        self::assertSame('error', $this->logs[0][0] ?? null);
    }

    public function testProviderExceptionDropsTheInitialValue(): void
    {
        $filter = $this->factory()->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => 'bug']),
        );

        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
        self::assertNull($filter->value);
        self::assertSame(['error', 'warning'], \array_column($this->logs, 0));
    }

    /**
     * @dataProvider controls
     */
    public function testProviderFlareExceptionPassesThrough(string $control): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('a wiring error');

        try {
            $this->factory()->createFromFilterModel(self::model(['control' => $control, 'value' => 'wiring']));
        } catch (FilterException $e) {
            self::fail('The wiring error was wrapped: ' . $e->getMessage());
        }
    }

    public static function controls(): iterable
    {
        yield 'without control' => [''];
        yield 'with control' => [self::BOOL_CONTROL];
    }

    public function testUnreadableFixedValueThrows(): void
    {
        $this->expectException(UnreadableValueException::class);

        $this->factory()->createFromFilterModel(self::model(['value' => 'garbage']));
    }

    public function testWithoutValueAControlledFilterWithAnInvalidAliasStillResolves(): void
    {
        // The backend form that repairs a legacy alias needs the filter.
        $filter = $this->factory()->createFromFilterModel(
            self::model(['alias' => 'price.min', 'control' => self::BOOL_CONTROL]),
            withValue: false,
        );

        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
    }

    public function testZeroIsAValidAlias(): void
    {
        self::assertSame('0', $this->factory()->create(element: self::BOOL_ELEMENT, alias: '0')->alias);
        self::assertSame(
            '0',
            $this->factory()->create(element: self::BOOL_ELEMENT, alias: '0', control: self::BOOL_CONTROL)->alias,
        );
    }

    public function testWithoutValueStillResolvesTheControl(): void
    {
        $filter = $this->factory()->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => '1']),
            withValue: false,
        );

        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
        self::assertNull($filter->value);
        self::assertSame([], $this->logs);
    }

    public function testWithoutValueNeverReadsAnUnreadableValue(): void
    {
        $filter = $this->factory()->createFromFilterModel(self::model(['value' => 'garbage']), withValue: false);

        self::assertNull($filter->value);

        $controlled = $this->factory()->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => 'garbage']),
            withValue: false,
        );

        self::assertNotNull($controlled->control, 'an unreadable initial value does not drop the control');
        self::assertSame([], $this->logs);
    }

    public function testWithoutValueAnUnknownControlThrows(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->createFromFilterModel(self::model(['control' => 'gone']), withValue: false);
    }
}

#[AsFilterElement(value: BoolValue::class)]
final class FactoryStubBoolElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {}
}

final class FactoryStubPlainElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {}
}

final class FactoryStubOptionsElement implements FilterElementInterface, OptionsContract
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('columns')->default([])->allowedTypes('array');
    }

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {}
}

final class FactoryStubBoolControl extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default('default')->allowedTypes('string');
    }

    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->set(FilterModel::class, static function (ConfigBuilder $config, FilterModel $model): void {
            $config->set('label', 'from model');
        });
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void {}

    public function decode(mixed $data, ControlScope $scope): ?BoolValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}

final class FactoryStubKeywordsControl extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void {}

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void {}

    public function decode(mixed $data, ControlScope $scope): ?KeywordsValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}

final class FactoryStubBoolProvider implements ValueProviderInterface
{
    public function provide(mixed $stored, FilterElementInterface $element): ?BoolValue
    {
        return match ($stored) {
            null, '' => null,
            '1' => new BoolValue(true),
            '0' => new BoolValue(false),
            'plain' => throw new FilterException('plain'),
            'bug' => throw new \InvalidArgumentException('a bug in the provider'),
            'wiring' => throw new FlareException('a wiring error'),
            default => throw new UnreadableValueException('unreadable'),
        };
    }

    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void {}
}
