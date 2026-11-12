<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Twig;

use Contao\ContentModel;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryModelFactory;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryRelationResolver;
use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use HeimrichHannot\FlareBundle\Exception\MissingModelRefError;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Registry\EngineModRegistry;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Twig\Runtime\FlareRuntime;
use Symfony\Component\EventDispatcher\EventDispatcher;

class FlareRuntimeTest extends ContaoTestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = new EventDispatcher();
    }

    /**
     * @param array<class-string, object> $adapters
     */
    private function runtime(array $adapters = []): FlareRuntime
    {
        $framework = $this->mockContaoFramework($adapters);

        return new FlareRuntime(
            $framework,
            $this->dispatcher,
            new ProjectorRegistry([]),
            new EntryRelationResolver($framework, new EntryModelFactory($framework)),
        );
    }

    private function list(): ListCompound
    {
        return new ListCompound(
            driver: $this->createStub(ListDriverInterface::class),
            type: 'test_list',
            dc: 'tl_test',
        );
    }

    public function testContentNeedsAModelRef(): void
    {
        $this->expectException(MissingModelRefError::class);

        $this->runtime()->getTlContent(new Entry(['id' => 1]));
    }

    public function testContentRendersTheElementsOfTheEntrysRecord(): void
    {
        $content = $this->mockAdapter(['findPublishedByPidAndTable']);
        $content->expects(self::once())->method('findPublishedByPidAndTable')->with(5, 'tl_test')->willReturn(null);

        $callable = $this->runtime([ContentModel::class => $content])
            ->getTlContent(EntryCollection::forTable('tl_test', [['id' => 5]])->first());

        self::assertSame('', $callable());
        self::assertSame('', $callable());
    }

    public function testContentIsLazy(): void
    {
        $content = $this->mockAdapter(['findPublishedByPidAndTable']);
        $content->expects(self::never())->method('findPublishedByPidAndTable');

        $this->runtime([ContentModel::class => $content])
            ->getTlContent(EntryCollection::forTable('tl_test', [['id' => 5]])->first());
    }

    public function testSchemaOrgFallsBackToTheContextOnlyInTheReader(): void
    {
        $seen = null;
        $this->dispatcher->addListener(
            ReaderSchemaOrgEvent::class,
            static function (ReaderSchemaOrgEvent $event) use (&$seen): void {
                $seen = $event;
                $event->data = ['@type' => 'Thing'];
            },
        );

        $list = $this->list();
        $entry = EntryCollection::forTable('tl_test', [['id' => 5]])->first();
        $engine = new Engine(
            new EngineModRegistry([]),
            new ProjectorRegistry([]),
            new ConfigurationIntent(),
            $list,
        );
        $context = ['entry' => $entry, 'flare' => $engine];
        $runtime = $this->runtime();

        self::assertNull($runtime->getSchemaOrg($context));
        self::assertNull($seen);

        $view = $this->createStub(ValidationView::class);
        self::assertSame(['@type' => 'Thing'], $runtime->getSchemaOrg($context + ['flare_reader' => $view]));
        self::assertSame($entry, $seen->entry);
        self::assertSame($list, $seen->list);
    }

    public function testSchemaOrgTakesExplicitArgumentsOutsideTheReader(): void
    {
        $seen = null;
        $this->dispatcher->addListener(
            ReaderSchemaOrgEvent::class,
            static function (ReaderSchemaOrgEvent $event) use (&$seen): void {
                $seen = $event;
                $event->data = ['@type' => 'Thing'];
            },
        );

        $list = $this->list();
        $entry = new Entry(['id' => 5]);

        self::assertSame(['@type' => 'Thing'], $this->runtime()->getSchemaOrg([], $entry, $list));
        self::assertSame($entry, $seen->entry);
        self::assertNull($this->runtime()->getSchemaOrg([], $entry));
    }
}
