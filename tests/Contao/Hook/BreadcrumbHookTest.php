<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Hook;

use Contao\Module;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Contao\Hook\BreadcrumbHook;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use HeimrichHannot\FlareBundle\Tests\Filter\Element\StubsContaoDcaTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\ContentModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Runs the breadcrumb over the reader resolver of a list on `tl_test`, through the real pipeline and an SQLite table.
 */
final class BreadcrumbHookTest extends ContaoTestCase
{
    use EnginePipelineTrait;
    use StubsContaoDcaTrait;

    private const ITEMS = [
        ['title' => 'Home', 'link' => 'Home'],
        ['title' => 'Reader', 'link' => 'Reader', 'data' => ['id' => 7]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stubDca('tl_test', ['fields' => \array_fill_keys(['id', 'alias', 'title', 'published'], [])]);
    }

    public function testTheLastItemGetsTheReaderTitle(): void
    {
        $result = $this->hook('visible')(self::ITEMS, $this->createMock(Module::class));

        self::assertSame(['T', 'T'], [$result[1]['title'], $result[1]['link']]);
        self::assertSame(self::ITEMS[0], $result[0]);
    }

    /**
     * Contao's template echoes `link` raw, so the title goes in HTML-escaped like Contao's own items, without
     * double-encoding the entities of an already encoded title.
     *
     * @dataProvider provideEscapedTitles
     */
    public function testTheReaderTitleIsEscaped(string $title, string $expected): void
    {
        $result = $this->hook('visible', title: $title)(self::ITEMS, $this->createMock(Module::class));

        self::assertSame([$expected, $expected], [$result[1]['title'], $result[1]['link']]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEscapedTitles(): iterable
    {
        yield 'a tag' => ['<img src=x onerror=alert(1)>', '&lt;img src=x onerror=alert(1)&gt;'];
        yield 'an ampersand and quotes' => ['Tom & "Jerry"', 'Tom &amp; &quot;Jerry&quot;'];
        yield 'an already encoded title' => ['Tom &amp; Jerry &lt;3', 'Tom &amp; Jerry &lt;3'];
    }

    /**
     * @dataProvider provideUnchanged
     */
    public function testItemsStayUnchanged(?string $autoItem, bool $hasReader, bool $failed, string $title): void
    {
        $hook = $this->hook($autoItem, $hasReader, $failed, $title);

        self::assertSame(self::ITEMS, $hook(self::ITEMS, $this->createMock(Module::class)));
    }

    /**
     * @return iterable<string, array{?string, bool, bool, string}>
     */
    public static function provideUnchanged(): iterable
    {
        yield 'no auto-item (the reader page opened directly)' => [null, true, false, 'T'];
        yield 'no reader on the page' => ['visible', false, false, 'T'];
        yield 'a failed list' => ['visible', true, true, 'T'];
        yield 'no entry (an unpublished row)' => ['hidden', true, false, 'T'];
        yield 'an empty title' => ['visible', true, false, ''];
    }

    /**
     * A hook over a published-only list with the rows 'visible' (published) and 'hidden'; the page meta title is
     * $title.
     */
    private function hook(
        ?string $autoItem,
        bool    $hasReader = true,
        bool    $failed = false,
        string  $title = 'T',
    ): BreadcrumbHook {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ReaderPageMetaEvent::class,
            static fn (ReaderPageMetaEvent $event) => $event->pageMeta->setTitle($title),
        );

        $connection = $this->createConnection([
            ['id' => 1, 'alias' => 'visible', 'published' => 1],
            ['id' => 2, 'alias' => 'hidden', 'published' => 0],
        ]);

        $content = new ContentModelFixture(['id' => 5], new ListModelFixture(['id' => 0, 'type' => 'test_list']));

        return new BreadcrumbHook($this->createReaderResolver(
            $connection,
            $dispatcher,
            $autoItem,
            filters: [self::publishedOnly()],
            failures: $failed ? [new FilterFailure('tl_flare_filter.3', 'broken')] : [],
            content: $hasReader ? $content : null,
        ));
    }
}
