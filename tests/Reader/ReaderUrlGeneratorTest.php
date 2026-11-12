<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Reader;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Event\ReaderUrlGeneratedEvent;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\Reader\NullReaderUrlGenerator;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlConfig;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ReaderUrlGeneratorTest extends TestCase
{
    private ?ReaderUrlGeneratedEvent $seen = null;

    /**
     * The generator calls PageModel::getAbsoluteUrl() (Contao 4.13 has no content URL generator), deprecated since
     * Contao 5.3: its mock triggers that deprecation.
     *
     * @group legacy
     */
    public function testReadsTheAutoItemFromTheRowAndDispatchesTheEntry(): void
    {
        $page = $this->createMock(PageModel::class);
        $page->method('getAbsoluteUrl')->with('/my-alias')->willReturn('https://example.org/reader/my-alias');
        $entry = (new EntryCollection([['id' => 1, 'alias' => 'my-alias']]))->first();
        self::assertNotNull($entry);

        $url = $this->generator($page)->generate($entry);

        self::assertSame('https://example.org/reader/my-alias', $url);
        self::assertSame([$entry, 'my-alias'], [$this->seen?->entry, $this->seen?->autoItem]);
    }

    public function testEmptyAutoItemHasNoLink(): void
    {
        $page = $this->createMock(PageModel::class);
        $page->expects(self::never())->method('getAbsoluteUrl');
        $entry = (new EntryCollection([['id' => 1, 'alias' => '']]))->first();
        self::assertNotNull($entry);

        self::assertNull($this->generator($page)->generate($entry));
        self::assertNull($this->seen, 'No event.');
    }

    public function testNullGeneratorHasNoLink(): void
    {
        $entry = (new EntryCollection([['id' => 1, 'alias' => 'my-alias']]))->first();
        self::assertNotNull($entry);

        self::assertNull((new NullReaderUrlGenerator())->generate($entry));
    }

    private function generator(PageModel $page): ReaderUrlGenerator
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ReaderUrlGeneratedEvent::class, function (ReaderUrlGeneratedEvent $e): void {
            $this->seen = $e;
        });

        return new ReaderUrlGenerator($dispatcher, new ReaderUrlConfig($page, 'alias'));
    }
}
