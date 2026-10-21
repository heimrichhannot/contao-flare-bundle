<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use Contao\Model;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\List\Collector\ListModelFilterCollector;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListModelFilterCollectorTest extends TestCase
{
    /** @var list<array{string, string}> */
    private array $logs = [];

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->logs = [];
        $this->dispatcher = new EventDispatcher();
    }

    private function collector(): ListModelFilterCollector
    {
        $elements = (new FilterElementRegistry())->add(new StubFilterElement(), null, 'stub');

        $logs = &$this->logs;
        $logger = new class ($logs) extends AbstractLogger {
            public function __construct(private array &$logs) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        $filterFactory = new FilterFactory(
            filterElementRegistry: $elements,
            filterControlRegistry: new FilterControlRegistry(),
            valueProviderRegistry: new ValueProviderRegistry(),
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver($this->dispatcher),
            logger: $logger,
        );

        return new ListModelFilterCollector(
            eventDispatcher: $this->dispatcher,
            filterFactory: $filterFactory,
            listDriverRegistry: new ListDriverRegistry(),
            logger: $logger,
        );
    }

    /**
     * Contao models without the framework: skip the constructor and set the row directly.
     *
     * @template T of Model
     * @param class-string<T> $class
     * @param array<string, mixed> $row
     * @return T
     */
    private static function model(string $class, array $row): Model
    {
        $model = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

        (function (array $row): void {
            $this->arrData = $row;
        })->call($model, $row);

        return $model;
    }

    private static function filterModel(int $id, string $type, string $control = ''): FilterModel
    {
        return self::model(FilterModel::class, [
            'id' => $id,
            'type' => $type,
            'control' => $control,
            'published' => '1',
        ]);
    }

    private static function listModel(): ListModel
    {
        return self::model(ListModel::class, ['id' => 5, 'type' => 'test_list']);
    }

    public function testCollectsFiltersByAliasAndDispatchesTheCollectedEvent(): void
    {
        $collectedModels = [];
        $this->dispatcher->addListener(
            FilterCollectedEvent::class,
            static function (FilterCollectedEvent $event) use (&$collectedModels): void {
                $collectedModels[] = $event->model->id;
            },
        );

        $collected = $this->collector()->collectFromModels(
            [self::filterModel(1, 'stub'), self::filterModel(2, 'stub')],
            self::listModel(),
        );

        self::assertSame([1, 2], \array_keys($collected->filters));
        self::assertSame('stub', $collected->filters[1]->type);
        self::assertSame([], $collected->failures);
        self::assertSame([1, 2], $collectedModels);
        self::assertSame([], $this->logs);
    }

    public function testFailingFixedFilterIsRecordedAsListFailure(): void
    {
        $collected = $this->collector()->collectFromModels(
            [self::filterModel(1, 'missing'), self::filterModel(2, 'stub')],
            self::listModel(),
        );

        self::assertSame([2], \array_keys($collected->filters));
        self::assertCount(1, $collected->failures);
        self::assertStringContainsString('tl_flare_filter.1', $collected->failures[0]);
        self::assertStringContainsString('Filter element type "missing" not found', $collected->failures[0]);
        self::assertCount(1, $this->logs);
        self::assertSame('error', $this->logs[0][0]);
        self::assertStringContainsString('tl_flare_list.5', $this->logs[0][1]);
    }

    public function testFailingElementOfAControlledFilterFailsTheList(): void
    {
        $collected = $this->collector()->collectFromModels(
            [self::filterModel(1, 'missing', 'flare_choice'), self::filterModel(2, 'stub')],
            self::listModel(),
        );

        self::assertSame([2], \array_keys($collected->filters));
        self::assertCount(1, $collected->failures);
        self::assertStringContainsString('tl_flare_filter.1', $collected->failures[0]);
        self::assertCount(1, $this->logs);
        self::assertSame('error', $this->logs[0][0]);
        self::assertStringContainsString('tl_flare_filter.1', $this->logs[0][1]);
    }

    public function testFailingControlIsAbsorbedAndTheFilterKeptWithoutIt(): void
    {
        $collected = $this->collector()->collectFromModels(
            [self::filterModel(1, 'stub', 'unknown_control')],
            self::listModel(),
        );

        self::assertSame([1], \array_keys($collected->filters));
        self::assertNull($collected->filters[1]->control);
        self::assertSame([], $collected->failures);
        self::assertCount(1, $this->logs);
        self::assertSame('warning', $this->logs[0][0]);
    }

    public function testDuplicateAliasFailsTheListInsteadOfOverwriting(): void
    {
        $first = self::model(FilterModel::class, [
            'id' => 1, 'type' => 'stub', 'alias' => 'dup', 'control' => '', 'published' => '1',
        ]);
        $second = self::model(FilterModel::class, [
            'id' => 2, 'type' => 'stub', 'alias' => 'dup', 'control' => '', 'published' => '1',
        ]);

        $collected = $this->collector()->collectFromModels([$first, $second], self::listModel());

        self::assertSame(['dup'], \array_keys($collected->filters));
        self::assertSame('tl_flare_filter.1', $collected->filters['dup']->source);
        self::assertCount(1, $collected->failures);
        self::assertStringContainsString('tl_flare_filter.2', $collected->failures[0]);
        self::assertStringContainsString('"dup"', $collected->failures[0]);
        self::assertCount(1, $this->logs);
        self::assertSame('error', $this->logs[0][0]);
    }

    public function testUnpublishedFiltersAreSkipped(): void
    {
        $unpublished = self::model(FilterModel::class, ['id' => 1, 'type' => 'missing', 'published' => '']);

        $collected = $this->collector()->collectFromModels([$unpublished], self::listModel());

        self::assertSame([], $collected->filters);
        self::assertSame([], $collected->failures);
    }
}
