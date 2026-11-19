<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Callback\FlareFilter;

use Contao\CoreBundle\Framework\Adapter;
use Contao\Message;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Contao\Inspection\Reporter\FilterIssueReporter;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter\ListCallbacks;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Tests\Contao\Inspection\BuildsFilterInspectorTrait;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

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
        self::assertSame([
            ['kind' => 'broken', 'icon' => null, 'text' => 'filter.info.broken.published %reason%=broken'],
        ], $context['notices']);
    }

    public function testEachNoticeIsRenderedWithItsText(): void
    {
        $recorded = [];

        $framework = $this->mockContaoFramework([
            FilterModel::class => $this->mockFilterModelAdapter([
                self::filterModel(1, 'throws', 'a', row: ['exclusive' => '1', 'outputs' => '']),
            ]),
            ListModel::class => $this->listAdapter(),
            Message::class => $this->recordMessages($recorded),
        ]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/contao?do=flare&table=tl_flare_filter&id=5'));

        $callbacks = new ListCallbacks(
            filterIssueReporter: new FilterIssueReporter(
                filterInspector: $this->createFilterInspector($framework),
                framework: $framework,
                requestStack: $requestStack,
                translator: self::echoTranslator(),
            ),
            translator: self::echoTranslator(),
            twig: self::twig(),
        );

        $html = $callbacks->listLabelLabel([
            'id' => 1,
            'pid' => 5,
            'type' => 'throws',
            'alias' => 'a',
            'published' => '1',
            'control' => '',
        ]);

        self::assertStringContainsString('<title>filter.info.broken.published %reason%=broken</title>', $html);
        self::assertStringContainsString('<title>filter.info.ineffective.exclusive_without_output</title>', $html);
        self::assertSame(1, \substr_count($html, 'lucide-bone-fracture-icon'));
        self::assertSame(1, \substr_count($html, 'lucide-engine-icon'));
    }

    public function testANoticeWithoutIconShowsItsKindsDefault(): void
    {
        $html = self::renderNotices([
            ['kind' => 'programming_error', 'icon' => null, 'text' => 'p'],
            ['kind' => 'duplicate_alias', 'icon' => null, 'text' => 'd'],
            ['kind' => 'broken', 'icon' => null, 'text' => 'b'],
            ['kind' => 'ineffective', 'icon' => null, 'text' => 'i'],
            ['kind' => 'warning', 'icon' => null, 'text' => 'w'],
        ]);

        foreach (['bug', 'copy-x', 'bone-fracture', 'engine', 'badge-exclamation-point'] as $icon) {
            self::assertSame(1, \substr_count($html, "lucide-{$icon}-icon"), $icon);
        }
    }

    public function testANoticeShowsItsOwnIcon(): void
    {
        $html = self::renderNotices([['kind' => 'warning', 'icon' => 'opacity', 'text' => 'w']]);

        self::assertStringContainsString('lucide-opacity-icon', $html);
        self::assertStringNotContainsString('lucide-badge-exclamation-point-icon', $html);
    }

    public function testAnIconWithoutPartialShowsTheKindsDefault(): void
    {
        $html = self::renderNotices([
            ['kind' => 'warning', 'icon' => 'no-such-icon', 'text' => 'w'],
            ['kind' => 'broken', 'icon' => '../filter_child_record', 'text' => 'b'],
        ]);

        self::assertSame(1, \substr_count($html, 'lucide-badge-exclamation-point-icon'));
        self::assertSame(1, \substr_count($html, 'lucide-bone-fracture-icon'));
    }

    /**
     * @param list<array{kind: string, icon: ?string, text: string}> $notices
     */
    private static function renderNotices(array $notices): string
    {
        return self::twig()->render('@HeimrichHannotFlare/backend/filter_child_record.html.twig', [
            'row' => ['id' => 1],
            'is_intrinsic' => false,
            'is_published' => true,
            'title' => 'title',
            'type_label' => 'type',
            'cut_label' => 'cut',
            'filter_alias' => 'a',
            'notices' => $notices,
        ]);
    }

    private static function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../../templates', 'HeimrichHannotFlare');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addGlobal('flare_env', new class {
            public function isContao57orHigher(): bool
            {
                return false;
            }
        });

        return $twig;
    }

    private function listAdapter(): Adapter
    {
        $adapter = $this->mockAdapter(['findByPk']);
        $adapter->expects(self::once())->method('findByPk')->with(5)->willReturn(self::listModel(5));

        return $adapter;
    }
}
