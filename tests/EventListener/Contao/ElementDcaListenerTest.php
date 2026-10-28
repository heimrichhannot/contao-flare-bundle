<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\Contao;

use Contao\CoreBundle\Framework\Adapter;
use Contao\Message;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\DataContainer\BackendElementScopeFactory;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueReporter;
use HeimrichHannot\FlareBundle\EventListener\Contao\ElementDcaListener;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\BuildsFilterInspectorTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ElementDcaListenerTest extends ContaoTestCase
{
    use BuildsFilterInspectorTrait;

    /** @var list<array{string, string}> */
    private array $recorded = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorded = [];
        $GLOBALS['TL_DCA']['tl_flare_list'] = ['fields' => [], 'palettes' => []];
        $GLOBALS['TL_DCA']['tl_flare_filter'] = ['fields' => [], 'palettes' => []];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA']['tl_flare_list'], $GLOBALS['TL_DCA']['tl_flare_filter']);

        parent::tearDown();
    }

    public function testTheFilterChildViewReportsTheOverview(): void
    {
        $listener = $this->listener(
            Request::create('/contao?do=flare&table=tl_flare_filter&id=5'),
            $this->mockFilterModelAdapter([self::filterModel(1, 'gone', 'a')]),
            $this->mockConfiguredAdapter(['findByPk' => self::listModel(5)]),
        );

        $listener('tl_flare_list');

        self::assertSame([['addError', 'list.info.broken_filters %count%=1']], $this->recorded);
    }

    public function testTheEditFormReportsTheFilter(): void
    {
        $row = self::filterModel(1, 'throws', 'a');

        $listener = $this->listener(
            Request::create('/contao?do=flare&table=tl_flare_filter&act=edit&id=1'),
            $this->mockFilterModelAdapter([$row], [1 => $row]),
            $this->mockConfiguredAdapter(['findByPk' => self::listModel(5)]),
        );

        $listener('tl_flare_filter');

        self::assertSame([['addError', 'filter.info.broken.published %reason%=broken']], $this->recorded);
    }

    public function testAFilterWithoutItsListReportsNothing(): void
    {
        $row = self::filterModel(1, 'throws', 'a');

        $listener = $this->listener(
            Request::create('/contao?do=flare&table=tl_flare_filter&act=edit&id=1'),
            $this->mockFilterModelAdapter([$row], [1 => $row]),
            $this->mockConfiguredAdapter(['findByPk' => null]),
        );

        $listener('tl_flare_filter');

        self::assertSame([], $this->recorded);
    }

    public function testAnInvalidRecordIdLoadsNothing(): void
    {
        foreach (['', '&id=abc', '&id=0', '&id=-3'] as $query)
        {
            $filters = $this->mockAdapter(['findBy', 'findByPk']);
            $filters->expects(self::never())->method('findByPk');

            $lists = $this->mockAdapter(['findByPk']);
            $lists->expects(self::never())->method('findByPk');

            $listener = $this->listener(
                Request::create('/contao?do=flare&table=tl_flare_filter&act=edit' . $query),
                $filters,
                $lists,
            );

            $listener('tl_flare_filter');
            $listener('tl_flare_list');
        }

        self::assertSame([], $this->recorded);
    }

    private function listener(Request $request, Adapter $filters, Adapter $lists): ElementDcaListener
    {
        $dispatcher = new EventDispatcher();

        $framework = $this->mockContaoFramework([
            FilterModel::class => $filters,
            ListModel::class => $lists,
            Message::class => $this->recordMessages($this->recorded),
        ]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ElementDcaListener(
            elementScopeFactory: new BackendElementScopeFactory($this->createFilterFactory()),
            eventDispatcher: $dispatcher,
            filterControlRegistry: new FilterControlRegistry(),
            filterElementRegistry: $this->createFilterElementRegistry(),
            filterIssueReporter: new FilterIssueReporter(
                filterInspector: $this->createFilterInspector($framework, $dispatcher),
                framework: $framework,
                requestStack: $requestStack,
                translator: self::echoTranslator(),
            ),
            framework: $framework,
            listExecutionContextFactory: new ListExecutionContextFactory($dispatcher),
            listFactory: $this->createListSpecBuilderFactory($dispatcher),
            listDriverRegistry: $this->createListDriverRegistry(),
            requestStack: $requestStack,
            valueProviderRegistry: new ValueProviderRegistry(),
        );
    }
}
