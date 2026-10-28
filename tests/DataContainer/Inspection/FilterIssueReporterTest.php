<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection;

use Contao\CoreBundle\Framework\Adapter;
use Contao\Message;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueReporter;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class FilterIssueReporterTest extends ContaoTestCase
{
    use BuildsFilterInspectorTrait;

    private const OVERVIEW = '/contao?do=flare&table=tl_flare_filter&id=5';
    private const EDIT = '/contao?do=flare&table=tl_flare_filter&act=edit&id=1';

    private EventDispatcher $dispatcher;

    /** @var list<array{string, string}> */
    private array $recorded = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = new EventDispatcher();
        $this->recorded = [];
    }

    public function testTheOverviewReportsEachIssueKindWithItsCount(): void
    {
        $this->dispatcher->addListener(FilterCollectedEvent::class, static function (FilterCollectedEvent $event): void {
            if ($event->model->id === 1) {
                throw new \Error('bug');
            }
        });
        $this->renameToDup(2, 3);

        $this->reporter([
            self::filterModel(1, 'ok', 'x'),
            self::filterModel(2, 'ok', 'b'),
            self::filterModel(3, 'ok', 'c'),
            self::filterModel(4, 'gone', 'd'),
            self::filterModel(5, 'ineffective', 'e'),
        ], Request::create(self::OVERVIEW))->reportOverview(self::listModel(5));

        self::assertSame([
            ['addError', 'list.info.programming_errors %count%=1'],
            ['addError', 'list.info.duplicate_filter_alias %alias%="&lt;dup&gt;"'],
            ['addError', 'list.info.broken_filters %count%=1'],
            ['addNew', 'list.info.ineffective_filters %count%=1'],
        ], $this->recorded);
    }

    public function testUnpublishedIssuesAddNoOverviewMessage(): void
    {
        $this->reporter([
            self::filterModel(1, 'gone', 'a', published: false),
            self::filterModel(2, 'ineffective', 'b', published: false),
        ], Request::create(self::OVERVIEW))->reportOverview(self::listModel(5));

        self::assertSame([], $this->recorded);
    }

    public function testNothingIsInspectedOrReportedOutsideTheFilterChildView(): void
    {
        $requests = [
            Request::create('/contao?do=flare&table=tl_flare_filter&act=edit&id=5'),
            Request::create('/contao?do=flare&table=tl_other&id=5'),
            Request::create(self::OVERVIEW, 'POST'),
        ];

        foreach ($requests as $request)
        {
            $filters = $this->mockAdapter(['findBy']);
            $filters->expects(self::never())->method('findBy');

            $this->reporter([self::filterModel(1, 'gone', 'a')], $request, filters: $filters)
                ->reportOverview(self::listModel(5));
        }

        self::assertSame([], $this->recorded);
    }

    public function testTheEditFormReportsEachIssueOfTheFilter(): void
    {
        $this->renameToDup(1, 2);
        $row = self::filterModel(1, 'throws', 'a');

        $this->reporter([$row, self::filterModel(2, 'ok', 'b')], Request::create(self::EDIT))
            ->reportFilter(self::listModel(5), $row);

        self::assertSame([
            ['addError', 'filter.info.duplicate_alias.published %alias%=&lt;dup&gt;'],
            ['addError', 'filter.info.broken.published %reason%=broken'],
        ], $this->recorded);
    }

    public function testIneffectiveFiltersAreReportedAsNew(): void
    {
        $row = self::filterModel(1, 'ineffective', 'a', published: false);

        $this->reporter([$row], Request::create(self::EDIT))->reportFilter(self::listModel(5), $row);

        self::assertSame([['addNew', 'stub.ineffective']], $this->recorded);
    }

    public function testTheEditFormIsReportedOnGetWithActEditOnly(): void
    {
        $row = self::filterModel(1, 'throws', 'a');

        foreach ([Request::create(self::EDIT, 'POST'), Request::create(self::OVERVIEW)] as $request)
        {
            $filters = $this->mockAdapter(['findBy']);
            $filters->expects(self::never())->method('findBy');

            $this->reporter([$row], $request, filters: $filters)->reportFilter(self::listModel(5), $row);
        }

        self::assertSame([], $this->recorded);
    }

    public function testRowNoticesAreKeyedByIssueKind(): void
    {
        $notices = $this->reporter(
            [self::filterModel(1, 'throws', 'a')],
            Request::create(self::OVERVIEW),
            lists: $this->listAdapter(),
        )->getRowNotices(['id' => 1, 'pid' => 5]);

        self::assertSame(['broken' => 'filter.info.broken.published %reason%=broken'], $notices);
    }

    public function testARowWithoutItsListHasNoNotices(): void
    {
        $notices = $this->reporter([self::filterModel(1, 'throws', 'a')], Request::create(self::OVERVIEW))
            ->getRowNotices(['id' => 1, 'pid' => 5]);

        self::assertSame([], $notices);
    }

    /**
     * @param list<FilterModel> $rows
     */
    private function reporter(
        array    $rows,
        Request  $request,
        ?Adapter $lists = null,
        ?Adapter $filters = null,
    ): FilterIssueReporter {
        $framework = $this->mockContaoFramework([
            FilterModel::class => $filters ?? $this->mockFilterModelAdapter($rows),
            ListModel::class => $lists ?? $this->mockConfiguredAdapter(['findByPk' => null]),
            Message::class => $this->recordMessages($this->recorded),
        ]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new FilterIssueReporter(
            filterInspector: $this->createFilterInspector($framework, $this->dispatcher),
            framework: $framework,
            requestStack: $requestStack,
            translator: self::echoTranslator(),
        );
    }

    private function renameToDup(int ...$ids): void
    {
        $this->dispatcher->addListener(FilterCollectedEvent::class, static function (FilterCollectedEvent $event) use ($ids): void {
            if (\in_array($event->model->id, $ids, true)) {
                $event->filter = new Filter(
                    element: $event->filter->element,
                    alias: '<dup>',
                    source: $event->filter->source,
                );
            }
        });
    }

    private function listAdapter(): Adapter
    {
        $adapter = $this->mockAdapter(['findByPk']);
        $adapter->expects(self::once())->method('findByPk')->with(5)->willReturn(self::listModel(5));

        return $adapter;
    }
}
