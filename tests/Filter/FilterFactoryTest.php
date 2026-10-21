<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
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
use HeimrichHannot\FlareBundle\Util\Str;
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
            filterTransformerResolver: new FilterTransformerResolver(new EventDispatcher()),
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

        self::assertSame($element, $filter->element);
        self::assertSame('my_element', $filter->type);
        self::assertSame(['a' => 1], $filter->config);
        self::assertSame('foo', $filter->alias);
        self::assertNull($filter->control);
        self::assertNull($filter->value);
    }

    public function testCreatesFromInstanceUsingItsClassNameAsType(): void
    {
        $element = new FactoryStubPlainElement();

        $filter = $this->factory()->create(element: $element);

        self::assertSame($element, $filter->element);
        self::assertSame(FactoryStubPlainElement::class, $filter->type);
        self::assertStringStartsWith('_.' . FactoryStubPlainElement::class . '_', $filter->alias);
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
            control: self::BOOL_CONTROL,
            value: new BoolValue(true),
        );

        self::assertSame($this->boolControl, $filter->control);
        self::assertSame(['label' => 'default'], $filter->controlConfig);
        self::assertEquals(new BoolValue(true), $filter->value);
    }

    public function testControlledFilterWithoutAliasGetsAFormValidAlias(): void
    {
        $filter = $this->factory()->create(element: self::BOOL_ELEMENT, control: self::BOOL_CONTROL);

        self::assertTrue(Str::isValidFormName($filter->alias), $filter->alias);
        self::assertStringStartsWith(self::BOOL_ELEMENT . '_', $filter->alias);

        $byInstance = $this->factory()->create(element: $this->boolElement, control: $this->boolControl);

        self::assertTrue(Str::isValidFormName($byInstance->alias), $byInstance->alias);
    }

    public function testUnknownControlNameIsRejected(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->create(element: self::BOOL_ELEMENT, control: 'gone');
    }

    public function testProgrammaticControlOfTheWrongClassIsRejected(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->create(element: self::BOOL_ELEMENT, control: new FactoryStubKeywordsControl());
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

        self::assertSame($this->boolElement, $fixed->element);
        self::assertSame(self::BOOL_ELEMENT, $fixed->type);
        self::assertSame('7', $fixed->alias);
        self::assertSame('tl_flare_filter.7', $fixed->source);
        self::assertNull($fixed->control);
        self::assertEquals(new BoolValue(true), $fixed->value);

        $initial = $this->factory()->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => '0']),
        );

        self::assertSame($this->boolControl, $initial->control);
        self::assertSame(['label' => 'from model'], $initial->controlConfig);
        self::assertEquals(new BoolValue(false), $initial->value);
        self::assertSame([], $this->logs);
    }

    public function testMissingValueColumnYieldsNoValue(): void
    {
        $filter = $this->factory()->createFromFilterModel(self::model([]));

        self::assertNull($filter->value);
    }

    public function testUnknownControlDegradesToNoControlAndNoValue(): void
    {
        $filter = $this->factory()->createFromFilterModel(self::model(['control' => 'gone', 'value' => '1']));

        self::assertSame($this->boolElement, $filter->element);
        self::assertNull($filter->control);
        self::assertSame([], $filter->controlConfig);
        self::assertNull($filter->value);
        $this->assertWarned();
    }

    public function testControlForAnotherValueClassDegradesLikeAnUnknownOne(): void
    {
        $filter = $this->factory()->createFromFilterModel(
            self::model(['control' => self::KEYWORDS_CONTROL, 'value' => '1']),
        );

        self::assertNull($filter->control);
        self::assertNull($filter->value);
        $this->assertWarned();
    }

    public function testUnreadableInitialValueDegradesToNoControlAndNoValue(): void
    {
        $filter = $this->factory()->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => 'garbage']),
        );

        self::assertNull($filter->control);
        self::assertNull($filter->value);
        $this->assertWarned();
    }

    public function testUnreadableFixedValueThrows(): void
    {
        $this->expectException(UnreadableValueException::class);

        $this->factory()->createFromFilterModel(self::model(['value' => 'garbage']));
    }

    public function testWithoutControlAndValueSkipsBoth(): void
    {
        $factory = $this->factory();

        $controlled = $factory->createFromFilterModel(
            self::model(['control' => self::BOOL_CONTROL, 'value' => '1']),
            withControlAndValue: false,
        );

        self::assertNull($controlled->control);
        self::assertSame([], $controlled->controlConfig);
        self::assertNull($controlled->value);

        $unreadable = $factory->createFromFilterModel(self::model(['value' => 'garbage']), withControlAndValue: false);

        self::assertNull($unreadable->value);
        self::assertSame([], $this->logs);
    }
}

#[AsFilterElement(value: BoolValue::class)]
final class FactoryStubBoolElement implements FilterElementInterface
{
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}
}

final class FactoryStubPlainElement implements FilterElementInterface
{
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}
}

final class FactoryStubBoolControl extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default('default')->allowedTypes('string');
    }

    public function configureTransformers(TransformerResolver $resolver): void
    {
        $resolver->for(FilterModel::class, static function (ConfigBuilder $config, FilterModel $model): void {
            $config->set('label', 'from model');
        });
    }

    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void {}

    public function decode(mixed $data, FilterScope $scope): ?BoolValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
    {
        return null;
    }
}

final class FactoryStubKeywordsControl extends AbstractFilterControl
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void {}

    public function decode(mixed $data, FilterScope $scope): ?KeywordsValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
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
            default => throw new UnreadableValueException('unreadable'),
        };
    }

    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void {}
}
