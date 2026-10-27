<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\DataContainer\BrokenFilterInspector;
use HeimrichHannot\FlareBundle\DataContainer\FilterDiagnosis;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class BrokenFilterInspectorTest extends TestCase
{
    use BuildsFilterContextsTrait;

    public function testHealthyFiltersAreNotMarked(): void
    {
        $diagnosis = $this->diagnose($this->spec(self::filter(new BrokenStubOkElement(), 1)));

        self::assertSame([], $diagnosis->rows);
        self::assertSame([], $diagnosis->listFailures);
    }

    public function testCollectionFailuresMarkTheirRows(): void
    {
        $spec = $this->spec(self::filter(new BrokenStubOkElement(), 1))
            ->withFailure(new FilterFailure('tl_flare_filter.4', 'The alias "dup" is already used by filter 1.'));

        $diagnosis = $this->diagnose($spec);

        self::assertSame('The alias "dup" is already used by filter 1.', $diagnosis->forRow(4)?->getReason());
        self::assertNull($diagnosis->forRow(1));
        self::assertCount(1, $diagnosis->listFailures);
    }

    public function testAFilterFailingWithItsDefaultValueIsMarked(): void
    {
        $diagnosis = $this->diagnose($this->spec(self::filter(new BrokenStubThrowingElement(), 2)));

        self::assertSame('broken', $diagnosis->forRow(2)?->getReason());
        self::assertCount(1, $diagnosis->listFailures);
    }

    public function testControlledFilterIsCheckedWithoutItsInitialValue(): void
    {
        $filter = self::filter(new BrokenStubValueSensitiveElement(), 3, new BoolValue(true))
            ->withControl(new ControlBinding(
                $this->createMock(FilterControlInterface::class),
                'flare_checkbox',
                new ComponentConfig(),
            ));

        $diagnosis = $this->diagnose($this->spec($filter));

        self::assertNull($diagnosis->forRow(3));
        self::assertSame([], $diagnosis->listFailures);
    }

    public function testAFixedValueIsCheckedAsTheFixedValue(): void
    {
        $diagnosis = $this->diagnose(
            $this->spec(self::filter(new BrokenStubValueSensitiveElement(), 3, new BoolValue(true))),
        );

        self::assertSame('value rejected', $diagnosis->forRow(3)?->getReason());
    }

    public function testAnAbortingFilterIsNotBroken(): void
    {
        $diagnosis = $this->diagnose($this->spec(self::filter(new BrokenStubAbortingElement(), 5)));

        self::assertSame([], $diagnosis->rows);
        self::assertSame([], $diagnosis->listFailures);
    }

    public function testAnUnexpectedExceptionMarksTheFilter(): void
    {
        $diagnosis = $this->diagnose($this->spec(self::filter(new BrokenStubRawElement(), 6)));

        self::assertSame('raw', $diagnosis->forRow(6)?->getReason());
    }

    public function testErrorsEscapeTheCheck(): void
    {
        $this->expectException(\Error::class);

        $this->diagnose($this->spec(self::filter(new BrokenStubErroringElement(), 7)));
    }

    public function testFailuresOfInlineFiltersFailTheListWithoutARow(): void
    {
        $inline = new Filter(
            element: new ElementBinding(new BrokenStubThrowingElement(), 'inline', new ComponentConfig()),
            alias: 'inline',
        );

        $diagnosis = $this->diagnose($this->spec($inline));

        self::assertSame([], $diagnosis->rows);
        self::assertCount(1, $diagnosis->listFailures);
    }

    public function testUnpublishedFiltersAreMarkedButDoNotFailTheList(): void
    {
        $diagnosis = $this->diagnose($this->spec(), [
            self::model(['id' => 8, 'type' => 'gone']),
            self::model(['id' => 9, 'type' => 'broken_throws']),
            self::model(['id' => 10, 'type' => 'broken_ok']),
        ]);

        self::assertNotNull($diagnosis->forRow(8));
        self::assertSame('broken', $diagnosis->forRow(9)?->getReason());
        self::assertNull($diagnosis->forRow(10));
        self::assertSame([], $diagnosis->listFailures);
    }

    public function testASpecThatCannotBeBuiltMarksNothing(): void
    {
        $diagnosis = $this->inspector()->diagnose(
            static fn (): ListSpec => throw new FlareException('No list driver found for type "gone"'),
            [self::model(['id' => 9, 'type' => 'broken_throws'])],
        );

        self::assertEquals(new FilterDiagnosis(), $diagnosis);
    }

    /**
     * @param iterable<FilterModel> $unpublished
     */
    private function diagnose(ListSpec $spec, iterable $unpublished = []): FilterDiagnosis
    {
        return $this->inspector()->diagnose(static fn (): ListSpec => $spec, $unpublished);
    }

    private function inspector(): BrokenFilterInspector
    {
        $elements = (new FilterElementRegistry())
            ->add(new BrokenStubOkElement(), new AsFilterElement(type: 'broken_ok'), 'broken_ok')
            ->add(new BrokenStubThrowingElement(), new AsFilterElement(type: 'broken_throws'), 'broken_throws');

        $factory = new FilterFactory(
            filterElementRegistry: $elements,
            filterControlRegistry: new FilterControlRegistry(),
            valueProviderRegistry: new ValueProviderRegistry(),
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher()),
            logger: new NullLogger(),
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        return new BrokenFilterInspector(
            // Only inspect() builds a spec; diagnose() is handed one.
            (new \ReflectionClass(ListSpecBuilderFactory::class))->newInstanceWithoutConstructor(),
            $factory,
            new FilterInvoker($this->createFilterContextFactory(), new FilterQueryBuilderFactory($connection)),
        );
    }

    private function spec(Filter ...$filters): ListSpec
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $keyed = [];
        foreach ($filters as $filter) {
            $keyed[$filter->alias] = $filter;
        }

        return new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test', filters: $keyed);
    }

    private static function filter(FilterElementInterface $element, int $id, ?ValueInterface $value = null): Filter
    {
        return new Filter(
            element: new ElementBinding($element, 'test_element', new ComponentConfig()),
            alias: "f{$id}",
            value: $value,
            source: "tl_flare_filter.{$id}",
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
        })->call($model, $row + ['control' => '', 'value' => '', 'published' => '']);

        return $model;
    }
}

class BrokenStubOkElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
    }
}

class BrokenStubThrowingElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        throw new FilterException('broken');
    }
}

class BrokenStubValueSensitiveElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        if ($value !== null) {
            throw new FilterException('value rejected');
        }
    }
}

class BrokenStubAbortingElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $builder->abort();
    }
}

class BrokenStubRawElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        throw new \RuntimeException('raw');
    }
}

class BrokenStubErroringElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        throw new \Error('bug');
    }
}
