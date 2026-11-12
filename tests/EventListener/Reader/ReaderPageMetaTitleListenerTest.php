<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\Reader;

use Contao\CoreBundle\String\HtmlDecoder;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\EventListener\Reader\ReaderPageMetaTitleListener;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use HeimrichHannot\FlareBundle\Tests\Fixture\ContentModelFixture;
use PHPUnit\Framework\TestCase;

final class ReaderPageMetaTitleListenerTest extends TestCase
{
    public function testTitleFromTheHeadlineOfTheRow(): void
    {
        $event = $this->dispatch(['id' => 1, 'headline' => \serialize(['unit' => 'h1', 'value' => 'Hello'])]);

        self::assertSame('Hello', $event->pageMeta->getTitle());
    }

    public function testTitleFallsBackToTheTitleColumn(): void
    {
        self::assertSame('Fallback', $this->dispatch(['id' => 1, 'title' => 'Fallback'])->pageMeta->getTitle());
    }

    public function testTitleFallsBackToTheId(): void
    {
        self::assertSame('7', $this->dispatch(['id' => 7])->pageMeta->getTitle());
    }

    public function testAnExistingTitleIsKept(): void
    {
        $event = $this->dispatch(['id' => 1, 'title' => 'Fallback'], (new ReaderPageMeta())->setTitle('Set'));

        self::assertSame('Set', $event->pageMeta->getTitle());
    }

    /**
     * Runs the listener over an entry of $row, with an HtmlDecoder that returns its argument.
     *
     * @param array<string, mixed> $row
     */
    private function dispatch(array $row, ?ReaderPageMeta $pageMeta = null): ReaderPageMetaEvent
    {
        $decoder = $this->createMock(HtmlDecoder::class);
        $decoder->method('inputEncodedToPlainText')->willReturnArgument(0);

        $entry = EntryCollection::forTable('tl_test', [$row])->first();
        self::assertNotNull($entry);

        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $event = new ReaderPageMetaEvent(
            contentModel: new ContentModelFixture(['id' => 5]),
            entry: $entry,
            list: new ListCompound(driver: $driver, type: 'test_list', dc: 'tl_test'),
            pageMeta: $pageMeta,
        );

        (new ReaderPageMetaTitleListener($decoder))($event);

        return $event;
    }
}
