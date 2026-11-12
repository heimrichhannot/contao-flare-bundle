<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Reader;

use Contao\ArticleModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\Date;
use Contao\Model;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contao\Reader\ReaderElementFinder;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ReaderElementFinderTest extends ContaoTestCase
{
    use EnginePipelineTrait;

    private const NOW = 1_800_000_000;

    /** @var list<array{string, list<int>}> table and ids per findMultipleByIds() call */
    private array $modelLoads = [];

    protected function tearDown(): void
    {
        unset($GLOBALS['objPage']);

        parent::tearDown();
    }

    public function testFindsTheOneVisibleReader(): void
    {
        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1]]);

        self::assertSame(20, (int) $finder->find()?->id);
    }

    public function testHiddenElementsAndArticlesAreSkipped(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7], ['id' => 2, 'pid' => 7, 'published' => 0]],
            [
                ['id' => 20, 'pid' => 1, 'invisible' => 1],
                ['id' => 21, 'pid' => 2],
                ['id' => 22, 'pid' => 1, 'start' => self::NOW + 3600],
                ['id' => 23, 'pid' => 1, 'stop' => self::NOW - 3600],
            ],
        );

        self::assertNull($finder->find());
    }

    public function testAnArticleOutsideItsPublishingWindowIsSkipped(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7, 'stop' => self::NOW - 60], ['id' => 2, 'pid' => 7, 'start' => self::NOW + 60]],
            [['id' => 20, 'pid' => 1], ['id' => 21, 'pid' => 2]],
        );

        self::assertNull($finder->find());
    }

    public function testElementsInsideTheirPublishingWindowCount(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7]],
            [['id' => 20, 'pid' => 1, 'start' => self::NOW - 60, 'stop' => self::NOW + 60]],
        );

        self::assertSame(20, (int) $finder->find()?->id);
    }

    public function testTwoVisibleReadersFindNothing(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7], ['id' => 2, 'pid' => 7]],
            [['id' => 20, 'pid' => 1], ['id' => 21, 'pid' => 2]],
        );

        self::assertNull($finder->find());
    }

    public function testTheModelsAreLoadedInOneCallPerTable(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7, 'sorting' => 1], ['id' => 2, 'pid' => 7, 'sorting' => 2]],
            [
                ['id' => 20, 'pid' => 1, 'sorting' => 1],
                ['id' => 21, 'pid' => 1, 'sorting' => 2],
                ['id' => 22, 'pid' => 2, 'sorting' => 1],
            ],
            hiddenElements: [20, 21],
        );

        self::assertSame(22, (int) $finder->find()?->id);
        self::assertSame([['tl_content', [20, 21, 22]], ['tl_article', [1, 2]]], $this->modelLoads);
    }

    public function testWithoutACandidateNoModelIsLoaded(): void
    {
        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1, 'type' => 'text']]);

        self::assertNull($finder->find());
        self::assertSame([], $this->modelLoads);
    }

    public function testAModelMissingFromTheRegistryAndTheDatabaseIsSkipped(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7]],
            [['id' => 20, 'pid' => 1, 'sorting' => 1], ['id' => 21, 'pid' => 1, 'sorting' => 2]],
            missingElements: [20],
        );

        self::assertSame(21, (int) $finder->find()?->id);
    }

    public function testAReaderOnAnotherPageIsIgnored(): void
    {
        $finder = $this->finder([['id' => 1, 'pid' => 8]], [['id' => 20, 'pid' => 1]]);

        self::assertNull($finder->find());
    }

    public function testAnUnsavedReaderIsIgnored(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7]],
            [['id' => 20, 'pid' => 1], ['id' => 21, 'pid' => 1, 'tstamp' => 0]],
        );

        self::assertSame(20, (int) $finder->find()?->id);
    }

    public function testALegacyEmptyPtableCounts(): void
    {
        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1, 'ptable' => '']]);

        self::assertSame(20, (int) $finder->find()?->id);
    }

    public function testANestedReaderIsIgnored(): void
    {
        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1, 'ptable' => 'tl_content']]);

        self::assertNull($finder->find());
    }

    public function testOtherElementTypesAreIgnored(): void
    {
        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1, 'type' => 'text']]);

        self::assertNull($finder->find());
    }

    public function testAProtectedReaderIsSkipped(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7]],
            [['id' => 20, 'pid' => 1, 'sorting' => 1], ['id' => 21, 'pid' => 1, 'sorting' => 2]],
            hiddenElements: [20],
        );

        self::assertSame(21, (int) $finder->find()?->id);
    }

    public function testAProtectedArticleHidesItsReader(): void
    {
        $finder = $this->finder(
            [['id' => 1, 'pid' => 7]],
            [['id' => 20, 'pid' => 1]],
            hiddenArticles: [1],
        );

        self::assertNull($finder->find());
    }

    public function testThePageMayBeAnIdOnTheMainRequest(): void
    {
        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1]], page: 7);

        self::assertSame(20, (int) $finder->find()?->id);
    }

    public function testThePageMayBeANumericStringOnTheMainRequest(): void
    {
        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1]], page: '7');

        self::assertSame(20, (int) $finder->find()?->id);
    }

    /**
     * @dataProvider provideNonCanonicalPageIds
     */
    public function testANonCanonicalPageIdFallsBackToObjPage(string $page): void
    {
        $GLOBALS['objPage'] = $this->mockClassWithProperties(PageModel::class, ['id' => 7]);

        $finder = $this->finder([['id' => 1, 'pid' => 9]], [['id' => 20, 'pid' => 1]], page: $page);

        self::assertNull($finder->find(), 'The finder must search page 7, not the attribute\'s page.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNonCanonicalPageIds(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-9'];
        yield 'leading zero' => ['09'];
        yield 'decimal' => ['9.0'];
        yield 'not a number' => ['nine'];
    }

    public function testThePageIsTakenFromTheMainRequestNotTheCurrentOne(): void
    {
        $main = new Request();
        $main->attributes->set('pageModel', $this->mockClassWithProperties(PageModel::class, ['id' => 7]));
        $sub = new Request();
        $sub->attributes->set('pageModel', 9);
        $stack = new RequestStack();
        $stack->push($main);
        $stack->push($sub);

        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1]], stack: $stack);

        self::assertSame(20, (int) $finder->find()?->id);
    }

    public function testThePageFallsBackToObjPage(): void
    {
        $GLOBALS['objPage'] = $this->mockClassWithProperties(PageModel::class, ['id' => 7]);

        $finder = $this->finder([['id' => 1, 'pid' => 7]], [['id' => 20, 'pid' => 1]], page: null);

        self::assertSame(20, (int) $finder->find()?->id);
    }

    public function testWithoutAPageNothingIsQueried(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method(self::anything());

        $finder = new ReaderElementFinder($connection, $this->mockContaoFramework(), new RequestStack());

        self::assertNull($finder->find());
    }

    /**
     * @param list<array<string, mixed>> $articles
     * @param list<array<string, mixed>> $elements
     * @param int|string|null            $page            the main request's pageModel id; null: no attribute
     * @param list<int>                  $hiddenElements  ids Controller::isVisibleElement() rejects
     * @param list<int>                  $hiddenArticles
     * @param list<int>                  $missingElements ids findMultipleByIds() does not return
     */
    private function finder(
        array         $articles,
        array         $elements,
        int|string|null $page = 7,
        array         $hiddenElements = [],
        array         $hiddenArticles = [],
        ?RequestStack $stack = null,
        array         $missingElements = [],
    ): ReaderElementFinder {
        $connection = $this->createConnection([]);
        $this->createReaderTables($connection, $articles, $elements);

        if ($stack === null) {
            $request = new Request();
            if ($page !== null) {
                $request->attributes->set('pageModel', $page);
            }
            $stack = new RequestStack();
            $stack->push($request);
        }

        $contentModels = $this->modelAdapter(ContentModel::class, 'tl_content', $missingElements);
        $articleModels = $this->modelAdapter(ArticleModel::class, 'tl_article', []);
        $controller = $this->mockAdapter(['isVisibleElement']);
        $controller->method('isVisibleElement')->willReturnCallback(
            static fn (object $model): bool => !\in_array(
                (int) $model->id,
                $model instanceof ArticleModel ? $hiddenArticles : $hiddenElements,
                true,
            ),
        );

        $framework = $this->mockContaoFramework([
            Date::class => $this->mockConfiguredAdapter(['floorToMinute' => self::NOW]),
            ContentModel::class => $contentModels,
            ArticleModel::class => $articleModels,
            Controller::class => $controller,
        ]);

        return new ReaderElementFinder($connection, $framework, $stack);
    }

    /**
     * An adapter whose findMultipleByIds() records its call and returns a model per id, null for none.
     *
     * @param class-string<Model> $class
     * @param list<int>           $missing
     */
    private function modelAdapter(string $class, string $table, array $missing): Adapter&MockObject
    {
        $adapter = $this->mockAdapter(['findMultipleByIds']);
        $adapter->method('findMultipleByIds')->willReturnCallback(function (array $ids) use ($class, $table, $missing) {
            $this->modelLoads[] = [$table, $ids];
            $found = \array_values(\array_diff($ids, $missing));
            $models = \array_map(fn (int $id) => $this->mockClassWithProperties($class, ['id' => $id]), $found);

            return $models === [] ? null : $models;
        });

        return $adapter;
    }
}
