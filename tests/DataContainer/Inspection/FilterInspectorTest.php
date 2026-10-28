<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterInspector;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssue;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueKind;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\ListInspection;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

final class FilterInspectorTest extends TestCase
{
    use BuildsFilterContextsTrait;

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testHealthyRowsHaveNoIssues(): void
    {
        $inspection = $this->inspect([self::model(1, 'ok', 'a'), self::model(2, 'ok', 'b', published: false)]);

        self::assertEquals(new ListInspection(), $inspection);
    }

    public function testAnUnknownTypeIsBroken(): void
    {
        $inspection = $this->inspect([self::model(1, 'gone', 'a')]);

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertTrue($inspection->forRow(1)[0]->published);
        self::assertSame(1, $inspection->count(FilterIssueKind::Broken));
    }

    public function testUnpublishedIssuesDoNotCount(): void
    {
        $inspection = $this->inspect([self::model(1, 'gone', 'a', published: false)]);

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertFalse($inspection->forRow(1)[0]->published);
        self::assertSame(0, $inspection->count(FilterIssueKind::Broken));
    }

    public function testTwoPublishedDuplicatesBlockTheList(): void
    {
        $inspection = $this->inspect([
            self::model(1, 'ok', 'dup'),
            self::model(2, 'ok', 'dup'),
            self::model(3, 'ok', 'dup', published: false),
        ]);

        foreach ([1, 2, 3] as $id) {
            self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, $id), "row {$id}");
        }

        self::assertSame('dup', $inspection->forRow(1)[0]->reason);
        self::assertFalse($inspection->forRow(3)[0]->published);
        self::assertSame(['dup'], $inspection->blockingAliases);
        self::assertSame(2, $inspection->count(FilterIssueKind::DuplicateAlias));
    }

    public function testOnePublishedDuplicateFlagsOnlyTheUnpublished(): void
    {
        $inspection = $this->inspect([
            self::model(1, 'ok', 'dup'),
            self::model(2, 'ok', 'dup', published: false),
            self::model(3, 'ok', 'dup', published: false),
        ]);

        self::assertSame([], $inspection->forRow(1));
        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 2));
        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 3));
        self::assertFalse($inspection->forRow(2)[0]->published);
        self::assertSame([], $inspection->blockingAliases);
        self::assertSame(0, $inspection->count(FilterIssueKind::DuplicateAlias));
    }

    public function testUnpublishedDuplicatesAloneAreNotFlagged(): void
    {
        $inspection = $this->inspect([
            self::model(2, 'ok', 'dup', published: false),
            self::model(3, 'ok', 'dup', published: false),
        ]);

        self::assertEquals(new ListInspection(), $inspection);
    }

    public function testARowThatCannotBeBuiltStillGroupsByItsFormName(): void
    {
        $inspection = $this->inspect([self::model(1, 'gone', 'dup'), self::model(2, 'ok', 'dup')]);

        self::assertSame([FilterIssueKind::DuplicateAlias, FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 2));
        self::assertSame(['dup'], $inspection->blockingAliases);
    }

    public function testAListenerRenamingAnAliasIsHonoured(): void
    {
        $this->dispatcher->addListener(FilterCollectedEvent::class, static function (FilterCollectedEvent $event): void {
            if ($event->model->id === 2) {
                $event->filter = new Filter(element: $event->filter->element, alias: 'dup', source: $event->filter->source);
            }
        });

        $inspection = $this->inspect([self::model(1, 'ok', 'dup'), self::model(2, 'ok', 'other')]);

        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 1));
        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 2));
    }

    public function testAProgrammingErrorMarksTheRow(): void
    {
        $this->throwErrorFor(4);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $inspection = $this->inspect([self::model(4, 'ok', 'a')], logger: $logger);

        self::assertSame([FilterIssueKind::ProgrammingError], self::kinds($inspection, 4));
        self::assertSame('bug', $inspection->forRow(4)[0]->reason);
        self::assertSame(1, $inspection->count(FilterIssueKind::ProgrammingError));
    }

    public function testErrorsEscapeInDebugMode(): void
    {
        $this->throwErrorFor(4);

        $this->expectException(\Error::class);

        $this->inspect([self::model(4, 'ok', 'a')], debug: true);
    }

    private function throwErrorFor(int $id): void
    {
        $this->dispatcher->addListener(FilterCollectedEvent::class, static function (FilterCollectedEvent $event) use ($id): void {
            if ($event->model->id === $id) {
                throw new \Error('bug');
            }
        });
    }

    /**
     * @param list<FilterModel> $models
     * @param (\Closure(array<string, Filter>): ListSpec)|null $buildScope
     */
    private function inspect(
        array            $models,
        ?\Closure        $buildScope = null,
        bool             $debug = false,
        ?LoggerInterface $logger = null,
    ): ListInspection {
        return $this->inspector($debug, $logger)->inspectModels(
            $models,
            $buildScope ?? static fn (array $filters): ListSpec => self::spec($filters),
        );
    }

    private function inspector(bool $debug = false, ?LoggerInterface $logger = null): FilterInspector
    {
        $elements = (new FilterElementRegistry())
            ->add(new InspectorStubOkElement(), new AsFilterElement(type: 'ok'), 'ok')
            ->add(new InspectorStubThrowingElement(), new AsFilterElement(type: 'throws'), 'throws');

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

        return new FilterInspector(
            eventDispatcher: $this->dispatcher,
            filterFactory: $factory,
            filterInvoker: new FilterInvoker(
                $this->createFilterContextFactory(),
                new FilterQueryBuilderFactory($connection),
            ),
            // Only inspect() builds the list scope; inspectModels() is handed a closure.
            listSpecBuilderFactory: (new \ReflectionClass(ListSpecBuilderFactory::class))
                ->newInstanceWithoutConstructor(),
            translator: new class implements TranslatorInterface {
                use TranslatorTrait;
            },
            logger: $logger ?? new NullLogger(),
            debug: $debug,
        );
    }

    /**
     * @param array<string, Filter> $filters
     */
    private static function spec(array $filters = []): ListSpec
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        return new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test', filters: $filters);
    }

    /**
     * @return list<FilterIssueKind>
     */
    private static function kinds(ListInspection $inspection, int $id): array
    {
        return \array_map(static fn (FilterIssue $issue): FilterIssueKind => $issue->kind, $inspection->forRow($id));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function model(int $id, string $type, string $alias, bool $published = true, array $row = []): FilterModel
    {
        $model = (new \ReflectionClass(FilterModel::class))->newInstanceWithoutConstructor();

        (function (array $row): void {
            $this->arrData = $row;
        })->call($model, $row + [
            'id' => $id,
            'type' => $type,
            'alias' => $alias,
            'published' => $published ? '1' : '',
            'control' => '',
            'value' => '',
        ]);

        return $model;
    }
}

class InspectorStubOkElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
    }
}

class InspectorStubThrowingElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        throw new FilterException('broken');
    }
}
