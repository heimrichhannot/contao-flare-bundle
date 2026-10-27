<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\Contao;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\EventListener\Contao\ElementDcaListener;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\ArchiveFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ElementDcaListenerTest extends TestCase
{
    public function testTheBackendScopeKnowsTheControlButReadsNoValue(): void
    {
        $scope = $this->createElementScope([
            'id' => 7,
            'type' => ArchiveFilterElement::TYPE,
            'control' => 'flare_choice',
            'value' => 'unreadable',
        ]);

        self::assertNotNull($scope);
        self::assertSame('flare_choice', $scope->filter->control?->type, 'the control decides the options');
        self::assertNull($scope->filter->value);
    }

    /**
     * Runs the listener's private backend scope factory with a real FilterFactory; the listener's other
     * collaborators are not involved and stay uninitialized.
     *
     * @param array<string, mixed> $row
     */
    private function createElementScope(array $row): ?ElementScope
    {
        $listener = (new \ReflectionClass(ElementDcaListener::class))->newInstanceWithoutConstructor();
        $factory = $this->createFilterFactory();

        (function () use ($factory): void {
            $this->filterFactory = $factory;
        })->call($listener);

        $model = (new \ReflectionClass(FilterModel::class))->newInstanceWithoutConstructor();
        (function () use ($row): void {
            $this->arrData = $row;
        })->call($model);

        $driver = $this->createStub(ListDriverInterface::class);
        $getSpec = static fn (): ListSpec => new ListSpec(driver: $driver, type: 'test', dc: 'tl_news');

        return (fn (): ?ElementScope => $this->createElementScope($getSpec, $model))->call($listener);
    }

    private function createFilterFactory(): FilterFactory
    {
        $elements = (new FilterElementRegistry())->add(
            new ArchiveFilterElement(),
            new AsFilterElement(type: ArchiveFilterElement::TYPE, value: ChoiceValue::class),
            ArchiveFilterElement::TYPE,
        );

        $controls = (new FilterControlRegistry())
            ->add($this->createMock(FilterControlInterface::class), 'flare_choice', ChoiceValue::class);

        $providers = (new ValueProviderRegistry())->add(new class implements ValueProviderInterface {
            public function provide(mixed $stored, FilterElementInterface $element): ?ValueInterface
            {
                throw new UnreadableValueException('Unreadable value.');
            }

            public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void {}
        }, ChoiceValue::class);

        return new FilterFactory(
            filterElementRegistry: $elements,
            filterControlRegistry: $controls,
            valueProviderRegistry: $providers,
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher()),
            logger: new NullLogger(),
        );
    }
}
