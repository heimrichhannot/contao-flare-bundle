<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\Framework\Adapter;
use Contao\Message;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\Reporter\FilterIssueReporter;
use HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter\ListCallbacks;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\BuildsFilterInspectorTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

final class ListCallbacksTest extends ContaoTestCase
{
    use BuildsFilterInspectorTrait;

    public function testTheRowReceivesItsNotices(): void
    {
        $recorded = [];

        $framework = $this->mockContaoFramework([
            FilterModel::class => $this->mockFilterModelAdapter([self::filterModel(1, 'throws', 'a')]),
            ListModel::class => $this->listAdapter(),
            Message::class => $this->recordMessages($recorded),
        ]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/contao?do=flare&table=tl_flare_filter&id=5'));

        $context = null;
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $parameters) use (&$context): string {
            $context = $parameters;

            return '';
        });

        $callbacks = new ListCallbacks(
            filterIssueReporter: new FilterIssueReporter(
                filterInspector: $this->createFilterInspector($framework),
                framework: $framework,
                requestStack: $requestStack,
                translator: self::echoTranslator(),
            ),
            translator: self::echoTranslator(),
            twig: $twig,
        );

        $callbacks->listLabelLabel([
            'id' => 1,
            'pid' => 5,
            'type' => 'throws',
            'alias' => 'a',
            'published' => '1',
            'control' => '',
        ]);

        self::assertIsArray($context);
        self::assertSame(['broken' => 'filter.info.broken.published %reason%=broken'], $context['notices']);
    }

    private function listAdapter(): Adapter
    {
        $adapter = $this->mockAdapter(['findByPk']);
        $adapter->expects(self::once())->method('findByPk')->with(5)->willReturn(self::listModel(5));

        return $adapter;
    }
}
