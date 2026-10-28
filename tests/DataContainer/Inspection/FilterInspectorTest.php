<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection;

use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssue;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueKind;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\ListInspection;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\OkElement;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\ThrowingElement;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class FilterInspectorTest extends ContaoTestCase
{
    use BuildsFilterInspectorTrait;

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testHealthyRowsHaveNoIssues(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'ok', 'a'), self::filterModel(2, 'ok', 'b', published: false)]);

        self::assertEquals(new ListInspection(), $inspection);
    }

    public function testAnUnknownTypeIsBroken(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'gone', 'a')]);

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertTrue($inspection->forRow(1)[0]->published);
        self::assertSame(1, $inspection->count(FilterIssueKind::Broken));
    }

    public function testUnpublishedIssuesDoNotCount(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'gone', 'a', published: false)]);

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertFalse($inspection->forRow(1)[0]->published);
        self::assertSame(0, $inspection->count(FilterIssueKind::Broken));
    }

    public function testTwoPublishedDuplicatesBlockTheList(): void
    {
        $inspection = $this->inspect([
            self::filterModel(1, 'ok', 'dup'),
            self::filterModel(2, 'ok', 'dup'),
            self::filterModel(3, 'ok', 'dup', published: false),
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
            self::filterModel(1, 'ok', 'dup'),
            self::filterModel(2, 'ok', 'dup', published: false),
            self::filterModel(3, 'ok', 'dup', published: false),
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
            self::filterModel(2, 'ok', 'dup', published: false),
            self::filterModel(3, 'ok', 'dup', published: false),
        ]);

        self::assertEquals(new ListInspection(), $inspection);
    }

    public function testARowThatCannotBeBuiltStillGroupsByItsFormName(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'gone', 'dup'), self::filterModel(2, 'ok', 'dup')]);

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

        $inspection = $this->inspect([self::filterModel(1, 'ok', 'dup'), self::filterModel(2, 'ok', 'other')]);

        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 1));
        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 2));
    }

    public function testAProgrammingErrorMarksTheRow(): void
    {
        $this->throwErrorFor(4);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $inspection = $this->inspect([self::filterModel(4, 'ok', 'a')], logger: $logger);

        self::assertSame([FilterIssueKind::ProgrammingError], self::kinds($inspection, 4));
        self::assertSame('bug', $inspection->forRow(4)[0]->reason);
        self::assertSame(1, $inspection->count(FilterIssueKind::ProgrammingError));
    }

    public function testErrorsEscapeInDebugMode(): void
    {
        $this->throwErrorFor(4);

        $this->expectException(\Error::class);

        $this->inspect([self::filterModel(4, 'ok', 'a')], debug: true);
    }

    public function testAFilterFailingWithItsDefaultValueIsBroken(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'throws', 'a')]);

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertSame('broken', $inspection->forRow(1)[0]->reason);
        self::assertSame(1, $inspection->count(FilterIssueKind::Broken));
    }

    public function testUnpublishedFiltersAreCheckedButDoNotCount(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'throws', 'a', published: false)]);

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertSame(0, $inspection->count(FilterIssueKind::Broken));
    }

    public function testControlledFilterIsCheckedWithoutItsInitialValue(): void
    {
        $inspection = $this->inspect([
            self::filterModel(1, 'sensitive', 'a', row: ['control' => 'flare_checkbox', 'value' => '1']),
        ]);

        self::assertSame([], $inspection->forRow(1));
    }

    public function testAFixedValueIsCheckedAsTheFixedValue(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'sensitive', 'a', row: ['value' => '1'])]);

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertSame('value rejected', $inspection->forRow(1)[0]->reason);
    }

    public function testAnAbortingFilterIsNotBroken(): void
    {
        self::assertEquals(new ListInspection(), $this->inspect([self::filterModel(1, 'aborts', 'a')]));
    }

    public function testAnUnexpectedExceptionIsBroken(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'raw', 'a')]);

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertSame('raw', $inspection->forRow(1)[0]->reason);
    }

    public function testAConditionErrorIsAProgrammingError(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'errors', 'a')]);

        self::assertSame([FilterIssueKind::ProgrammingError], self::kinds($inspection, 1));
        self::assertSame('bug', $inspection->forRow(1)[0]->reason);
        self::assertSame(1, $inspection->count(FilterIssueKind::ProgrammingError));
    }

    public function testAConditionErrorEscapesInDebugMode(): void
    {
        $this->expectException(\Error::class);

        $this->inspect([self::filterModel(1, 'errors', 'a')], debug: true);
    }

    public function testDescribesAFilterWithoutControlAndValue(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'ineffective', 'a')]);

        self::assertSame([FilterIssueKind::Ineffective], self::kinds($inspection, 1));
        self::assertSame('stub.ineffective', $inspection->forRow(1)[0]->reason);
        self::assertSame(1, $inspection->count(FilterIssueKind::Ineffective));
    }

    public function testEffectiveFiltersAreNotIneffective(): void
    {
        $inspection = $this->inspect([
            self::filterModel(1, 'ineffective', 'a', row: ['value' => '1']),
            self::filterModel(2, 'ineffective', 'b', row: ['control' => 'flare_checkbox']),
        ]);

        self::assertEquals(new ListInspection(), $inspection);
    }

    public function testUnpublishedIneffectiveFiltersDoNotCount(): void
    {
        $inspection = $this->inspect([self::filterModel(1, 'ineffective', 'a', published: false)]);

        self::assertSame([FilterIssueKind::Ineffective], self::kinds($inspection, 1));
        self::assertSame(0, $inspection->count(FilterIssueKind::Ineffective));
    }

    public function testFiltersOfTheDriverCountWithoutARow(): void
    {
        $inline = new Filter(
            element: new ElementBinding(new ThrowingElement(), 'throws', new ComponentConfig()),
            alias: 'inline',
        );

        $inspection = $this->inspect(
            [self::filterModel(1, 'ok', 'a')],
            static fn (array $filters): ListSpec => self::spec($filters + ['inline' => $inline]),
        );

        self::assertSame([], $inspection->rows);
        self::assertSame(1, $inspection->count(FilterIssueKind::Broken));
    }

    public function testOnlyTheFirstOfADuplicateGroupEntersTheScope(): void
    {
        $received = null;

        $inspection = $this->inspect(
            [
                self::filterModel(1, 'ok', 'dup'),
                self::filterModel(2, 'throws', 'dup'),
                self::filterModel(3, 'ok', 'b', published: false),
            ],
            static function (array $filters) use (&$received): ListSpec {
                $received = $filters;

                return self::spec($filters);
            },
        );

        self::assertIsArray($received);
        self::assertSame(['dup'], \array_keys($received));
        self::assertSame('tl_flare_filter.1', $received['dup']->source);
        // The later duplicate is still checked, with the scope as its list.
        self::assertSame([FilterIssueKind::DuplicateAlias, FilterIssueKind::Broken], self::kinds($inspection, 2));
    }

    public function testWithoutAListScopeRowIssuesAreStillReported(): void
    {
        $inspection = $this->inspect(
            [
                self::filterModel(1, 'gone', 'a'),
                self::filterModel(2, 'ok', 'dup'),
                self::filterModel(3, 'ok', 'dup'),
                self::filterModel(4, 'ineffective', 'c'),
                self::filterModel(5, 'throws', 'd'),
            ],
            static fn (): ListSpec => throw new FlareException('No list driver found for type "gone"'),
        );

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 2));
        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 3));
        self::assertSame([FilterIssueKind::Ineffective], self::kinds($inspection, 4));
        self::assertSame([], $inspection->forRow(5), 'no conditions are built without a list');
        self::assertSame(0, $inspection->count(FilterIssueKind::ProgrammingError));
    }

    public function testAScopeErrorIsAListLevelProgrammingError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $inspection = $this->inspect(
            [self::filterModel(1, 'ok', 'a')],
            static fn (): ListSpec => throw new \Error('driver bug'),
            logger: $logger,
        );

        self::assertSame([], $inspection->rows);
        self::assertSame(1, $inspection->count(FilterIssueKind::ProgrammingError));
    }

    public function testScopeErrorsEscapeInDebugMode(): void
    {
        $this->expectException(\Error::class);

        $this->inspect([self::filterModel(1, 'ok', 'a')], static fn (): ListSpec => throw new \Error('driver bug'), debug: true);
    }

    public function testAReplacedFilterWithoutASourceKeepsItsRow(): void
    {
        $this->dropSources();

        $inspection = $this->inspect([
            self::filterModel(1, 'ineffective', 'a', published: false),
            self::filterModel(2, 'throws', 'b'),
        ]);

        self::assertSame([FilterIssueKind::Ineffective], self::kinds($inspection, 1));
        self::assertSame(0, $inspection->count(FilterIssueKind::Ineffective), 'an unpublished filter never counts');
        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 2));
        self::assertSame(1, $inspection->count(FilterIssueKind::Broken));
    }

    public function testARowWithoutASourceIsCheckedBesideAFilterOfTheDriver(): void
    {
        $this->dropSources();

        $inline = new Filter(
            element: new ElementBinding(new OkElement(), 'ok', new ComponentConfig()),
            alias: 'inline',
        );

        $inspection = $this->inspect(
            [self::filterModel(1, 'throws', 'a', published: false)],
            static fn (array $filters): ListSpec => self::spec($filters + ['inline' => $inline]),
        );

        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
    }

    public function testInspectLoadsEverySavedRowAndBuildsTheListLikeTheRuntime(): void
    {
        $filters = $this->mockAdapter(['findBy']);
        $filters
            ->expects(self::once())
            ->method('findBy')
            ->with(['pid=?', 'tstamp>0'], [5], ['order' => 'sorting'])
            ->willReturn([
                self::filterModel(1, 'throws', 'a'),
                self::filterModel(2, 'ok', 'dup'),
                self::filterModel(3, 'ok', 'dup'),
            ]);

        $inspection = $this->createFilterInspector($this->mockContaoFramework([FilterModel::class => $filters]), $this->dispatcher)
            ->inspect(self::listModel(5));

        // Only a built list scope lets the conditions of row 1 be checked.
        self::assertSame([FilterIssueKind::Broken], self::kinds($inspection, 1));
        self::assertSame([FilterIssueKind::DuplicateAlias], self::kinds($inspection, 2));
        self::assertSame(['dup'], $inspection->blockingAliases);
    }

    public function testAListWithoutRowsHasNoIssues(): void
    {
        $filters = $this->mockConfiguredAdapter(['findBy' => null]);

        $inspection = $this->createFilterInspector($this->mockContaoFramework([FilterModel::class => $filters]), $this->dispatcher)
            ->inspect(self::listModel(5));

        self::assertEquals(new ListInspection(), $inspection);
    }

    public function testInspectionsAreMemoizedPerListUntilReset(): void
    {
        $filters = $this->mockAdapter(['findBy']);
        $filters->expects(self::exactly(3))->method('findBy')->willReturn([self::filterModel(1, 'ok', 'a')]);

        $inspector = $this->createFilterInspector($this->mockContaoFramework([FilterModel::class => $filters]), $this->dispatcher);

        $inspector->inspect(self::listModel(5));
        $inspector->inspect(self::listModel(5));
        $inspector->inspect(self::listModel(6));
        $inspector->reset();
        $inspector->inspect(self::listModel(5));
    }

    private function dropSources(): void
    {
        $this->dispatcher->addListener(FilterCollectedEvent::class, static function (FilterCollectedEvent $event): void {
            $event->filter = new Filter(element: $event->filter->element, alias: $event->filter->alias);
        });
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
        $framework = $this->mockContaoFramework([FilterModel::class => $this->mockFilterModelAdapter([])]);

        return $this->createFilterInspector($framework, $this->dispatcher, $debug, $logger)->inspectModels(
            $models,
            $buildScope ?? static fn (array $filters): ListSpec => self::spec($filters),
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
}
