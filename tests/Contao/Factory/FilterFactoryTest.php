<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Factory;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Contao\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory as CoreFilterFactory;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\Fixture\FilterModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\Filter\FactoryStubBoolControl;
use HeimrichHannot\FlareBundle\Tests\Fixture\Filter\FactoryStubBoolElement;
use HeimrichHannot\FlareBundle\Tests\Fixture\Filter\FactoryStubBoolProvider;
use HeimrichHannot\FlareBundle\Tests\Fixture\Filter\FactoryStubKeywordsControl;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

    private function factory(): FilterFactory
    {
        $elements = (new FilterElementRegistry())->add(
            $this->boolElement,
            new AsFilterElement(type: self::BOOL_ELEMENT, value: BoolValue::class),
            self::BOOL_ELEMENT,
        );

        $controls = (new FilterControlRegistry())
            ->add($this->boolControl, self::BOOL_CONTROL, BoolValue::class)
            ->add(new FactoryStubKeywordsControl(), self::KEYWORDS_CONTROL, KeywordsValue::class);

        $logs = &$this->logs;
        $logger = new class ($logs) extends AbstractLogger {
            public function __construct(private array &$logs) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        return new FilterFactory(
            filterFactory: new CoreFilterFactory(
                filterElementRegistry: $elements,
                filterControlRegistry: $controls,
                filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
                filterTransformerResolver: new FilterTransformerResolver(
                    new SourceTransformer(),
                    new EventDispatcher(),
                ),
            ),
            valueProviderRegistry: (new ValueProviderRegistry())->add(new FactoryStubBoolProvider(), BoolValue::class),
            logger: $logger,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function model(array $row): FilterModelFixture
    {
        return new FilterModelFixture($row + ['id' => 7, 'type' => self::BOOL_ELEMENT, 'control' => '']);
    }

    private function assertWarned(): void
    {
        self::assertCount(1, $this->logs);
        self::assertSame('warning', $this->logs[0][0]);
        self::assertStringContainsString('tl_flare_filter.7', $this->logs[0][1]);
    }

    public function testControlledFilterModelWithAnInvalidFormNameIsRejected(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('"price.min"');

        $this->factory()->create(self::model(['alias' => 'price.min', 'control' => self::BOOL_CONTROL]));
    }

    public function testValueComesFromTheProviderOfTheElementsValueClass(): void
    {
        $fixed = $this->factory()->create(self::model(['value' => '1']));

        self::assertSame($this->boolElement, $fixed->element->component);
        self::assertSame(self::BOOL_ELEMENT, $fixed->element->type);
        self::assertSame('7', $fixed->alias);
        self::assertSame('tl_flare_filter.7', $fixed->source);
        self::assertNull($fixed->control);
        self::assertEquals(new BoolValue(true), $fixed->value);

        $initial = $this->factory()->create(
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
        $filter = $this->factory()->create(self::model([]));

        self::assertNull($filter->value);
    }

    public function testUnknownControlThrows(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->create(self::model(['control' => 'gone', 'value' => '1']));
    }

    public function testControlForAnotherValueClassThrows(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->create(self::model(['control' => self::KEYWORDS_CONTROL, 'value' => '1']));
    }

    public function testUnreadableInitialValueKeepsTheControl(): void
    {
        $filter = $this->factory()->create(
            self::model(['control' => self::BOOL_CONTROL, 'value' => 'garbage']),
        );

        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
        self::assertNull($filter->value);
        $this->assertWarned();
    }

    public function testInitialValueFailingWithAPlainFilterExceptionKeepsTheControl(): void
    {
        $filter = $this->factory()->create(
            self::model(['control' => self::BOOL_CONTROL, 'value' => 'plain']),
        );

        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
        self::assertNull($filter->value);
        $this->assertWarned();
    }

    public function testProviderExceptionIsWrappedForAFixedValue(): void
    {
        try {
            $this->factory()->create(self::model(['value' => 'bug']));
            self::fail('No exception thrown.');
        } catch (FilterException $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
            self::assertSame('tl_flare_filter.7', $e->getSource());
        }

        self::assertSame('error', $this->logs[0][0] ?? null);
    }

    public function testProviderExceptionDropsTheInitialValue(): void
    {
        $filter = $this->factory()->create(
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
            $this->factory()->create(self::model(['control' => $control, 'value' => 'wiring']));
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

        $this->factory()->create(self::model(['value' => 'garbage']));
    }

    public function testWithoutValueAControlledFilterWithAnInvalidAliasIsRejected(): void
    {
        // A legacy alias must be renamed; the backend form does not get the filter either.
        $this->expectException(FlareException::class);

        $this->factory()->create(
            self::model(['alias' => 'price.min', 'control' => self::BOOL_CONTROL]),
            withValue: false,
        );
    }

    public function testWithoutValueStillResolvesTheControl(): void
    {
        $filter = $this->factory()->create(
            self::model(['control' => self::BOOL_CONTROL, 'value' => '1']),
            withValue: false,
        );

        self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
        self::assertNull($filter->value);
        self::assertSame([], $this->logs);
    }

    public function testWithoutValueNeverReadsAnUnreadableValue(): void
    {
        $filter = $this->factory()->create(self::model(['value' => 'garbage']), withValue: false);

        self::assertNull($filter->value);

        $controlled = $this->factory()->create(
            self::model(['control' => self::BOOL_CONTROL, 'value' => 'garbage']),
            withValue: false,
        );

        self::assertNotNull($controlled->control, 'an unreadable initial value does not drop the control');
        self::assertSame([], $this->logs);
    }

    public function testWithoutValueAnUnknownControlThrows(): void
    {
        $this->expectException(FlareException::class);

        $this->factory()->create(self::model(['control' => 'gone']), withValue: false);
    }
}
