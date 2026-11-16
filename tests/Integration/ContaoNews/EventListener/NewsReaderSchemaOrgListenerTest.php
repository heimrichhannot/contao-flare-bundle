<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Integration\ContaoNews\EventListener;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Model;
use Contao\News;
use Contao\NewsModel;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use HeimrichHannot\FlareBundle\Integration\ContaoNews\EventListener\NewsReaderSchemaOrgListener;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Tests\Fixture\TestModelFixture;

class NewsReaderSchemaOrgListenerTest extends ContaoTestCase
{
    public function testAnEntryOfAnotherTableIsLeftAlone(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects(self::never())->method('getAdapter');

        $event = $this->event(self::entry('tl_test', ['id' => 7]));
        (new NewsReaderSchemaOrgListener($framework))($event);

        self::assertSame(['kept' => true], $event->data);
    }

    public function testAnEntryWithoutModelRefIsLeftAlone(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects(self::never())->method('getAdapter');

        $event = $this->event(new Entry(['id' => 7]));
        (new NewsReaderSchemaOrgListener($framework))($event);

        self::assertSame(['kept' => true], $event->data);
    }

    public function testTheRecordIsLoadedThroughTheFinderOfTheRegisteredClass(): void
    {
        // A subclass of NewsModel, as a multilingual class would be: the finder of the *named* class is used.
        $model = $this->createMock(NewsModel::class);
        $class = $model::class;

        $finder = $this->mockAdapter(['findById']);
        $finder->expects(self::once())->method('findById')->with(self::identicalTo(7))->willReturn($model);

        $news = $this->mockAdapter(['getSchemaOrgData']);
        $news
            ->expects(self::once())
            ->method('getSchemaOrgData')
            ->with(self::identicalTo($model))
            ->willReturn(['@type' => 'NewsArticle']);

        $event = $this->event(self::entry('tl_news', ['id' => 7]));
        $this->listener($class, $finder, $news)($event);

        self::assertSame(['@type' => 'NewsArticle'], $event->data);
    }

    public function testAStringIdIsPassedUnchanged(): void
    {
        $model = $this->createMock(NewsModel::class);

        $finder = $this->mockAdapter(['findById']);
        $finder->expects(self::once())->method('findById')->with(self::identicalTo('7'))->willReturn($model);

        $news = $this->mockConfiguredAdapter(['getSchemaOrgData' => ['@type' => 'NewsArticle']]);

        $event = $this->event(self::entry('tl_news', ['id' => '7']));
        $this->listener($model::class, $finder, $news)($event);

        self::assertSame(['@type' => 'NewsArticle'], $event->data);
    }

    public function testAMissingRecordYieldsNoData(): void
    {
        $finder = $this->mockAdapter(['findById']);
        $finder->expects(self::once())->method('findById')->willReturn(null);

        $news = $this->mockAdapter(['getSchemaOrgData']);
        $news->expects(self::never())->method('getSchemaOrgData');

        $event = $this->event(self::entry('tl_news', ['id' => 7]));
        $this->listener(NewsModel::class, $finder, $news)($event);

        self::assertSame(['kept' => true], $event->data);
    }

    public function testAModelOfAnotherClassYieldsNoData(): void
    {
        $finder = $this->mockAdapter(['findById']);
        $finder->expects(self::once())->method('findById')->willReturn($this->createMock(TestModelFixture::class));

        $news = $this->mockAdapter(['getSchemaOrgData']);
        $news->expects(self::never())->method('getSchemaOrgData');

        $event = $this->event(self::entry('tl_news', ['id' => 7]));
        $this->listener(TestModelFixture::class, $finder, $news)($event);

        self::assertSame(['kept' => true], $event->data);
    }

    public function testABrokenModelRegistrationFailsTheRequest(): void
    {
        $model = $this->mockAdapter(['getClassFromTable']);
        $model->method('getClassFromTable')->willThrowException(new \RuntimeException('no model'));

        $listener = new NewsReaderSchemaOrgListener($this->mockContaoFramework([Model::class => $model]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no model');

        $listener($this->event(self::entry('tl_news', ['id' => 7])));
    }

    private function listener(string $class, Adapter $finder, Adapter $news): NewsReaderSchemaOrgListener
    {
        $model = $this->mockAdapter(['getClassFromTable']);
        $model->expects(self::once())->method('getClassFromTable')->with('tl_news')->willReturn($class);

        // No adapter for NewsModel::class unless it is the registered class: the table guard needs none.
        return new NewsReaderSchemaOrgListener($this->mockContaoFramework([
            Model::class => $model,
            $class => $finder,
            News::class => $news,
        ]));
    }

    private function event(Entry $entry): ReaderSchemaOrgEvent
    {
        $list = new ListCompound(
            driver: $this->createStub(ListDriverInterface::class),
            type: 'test_list',
            dc: 'tl_news',
        );

        return new ReaderSchemaOrgEvent($list, $entry, ['kept' => true]);
    }

    /** @param array<string, mixed> $row */
    private static function entry(string $table, array $row): Entry
    {
        $entry = EntryCollection::forTable($table, [$row])->first();
        self::assertNotNull($entry);

        return $entry;
    }
}
