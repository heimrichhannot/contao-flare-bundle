<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\ListEntry;

use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryLookup;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\Event\ListDriverEntriesEvent;
use HeimrichHannot\FlareBundle\Exception\InvalidLookupIdError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\SpyTagger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * Runs the lookup over the real loader, query director and an SQLite table: rows 1..3 published, 4 unpublished.
 */
final class EntryLookupTest extends TestCase
{
    use EnginePipelineTrait;

    public function testMembersComeBackInTheGivenOrderInOneQuery(): void
    {
        $result = $this->lookup()->lookup([3, '1', 2]);

        self::assertSame([3, 1, 2], self::ids($result));
        self::assertSame(1, $this->queries);
    }

    public function testNonMembersAreMissing(): void
    {
        $lookup = $this->lookup();

        self::assertSame([1], self::ids($lookup->lookup([4, 1])));
        self::assertNull($lookup->lookup(4));
    }

    public function testASingleIdReturnsItsEntry(): void
    {
        $entry = $this->lookup()->lookup('2');

        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame(2, (int) $entry->row['id']);
    }

    public function testDuplicatesCollapseAndIdenticalLookupsQueryOnce(): void
    {
        $lookup = $this->lookup();

        self::assertSame([2], self::ids($lookup->lookup([2, '2', 2])));

        $lookup->lookup([2]);
        $lookup->lookup(2);

        self::assertSame(1, $this->queries);
    }

    /**
     * @dataProvider provideInvalidIds
     */
    public function testInvalidIdsThrow(mixed $ids): void
    {
        $this->expectException(InvalidLookupIdError::class);
        $this->expectExceptionMessage('Deserialize a stored value first');

        $this->lookup()->lookup($ids);
    }

    public static function provideInvalidIds(): iterable
    {
        yield 'serialized' => ['a:2:{i:0;s:1:"1";i:1;s:1:"2";}'];
        yield 'non-numeric in a list' => [['1', 'x']];
        yield 'decimal string' => ['1.5'];
        yield 'float' => [1.5];
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'bool' => [true];
        yield 'null in a list' => [[null]];
        yield 'trailing newline' => ["1\n"];
    }

    /**
     * Through a real Twig environment, which calls methods without strict_types.
     *
     * @dataProvider provideInvalidTwigIds
     */
    public function testInvalidIdsThrowThroughTwig(mixed $ids): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '{% set r = l.lookup(v) %}']), ['strict_variables' => true]);

        try
        {
            $twig->render('t', ['l' => $this->lookup(), 'v' => $ids]);
            self::fail('No error thrown.');
        }
        catch (RuntimeError $e)
        {
            self::assertInstanceOf(InvalidLookupIdError::class, $e->getPrevious());
        }
    }

    public static function provideInvalidTwigIds(): iterable
    {
        yield 'bool' => [true];
        yield 'null' => [null];
        yield 'serialized' => ['a:1:{i:0;s:1:"1";}'];
    }

    public function testEmptyLookupQueriesNothing(): void
    {
        $events = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ListDriverEntriesEvent::class, static function () use (&$events): void {
            ++$events;
        });

        $result = $this->lookup($dispatcher, $tagger)->lookup([]);

        self::assertInstanceOf(EntryCollection::class, $result);
        self::assertCount(0, $result);
        self::assertTrue($result->isSealed());
        self::assertSame([0, 0], [$this->queries, $events]);
        self::assertSame([], $tagger->tagged);
    }

    public function testMembersHaveLinksRunTheEventAndAreTagged(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListDriverEntriesEvent::class,
            static fn (ListDriverEntriesEvent $e) => $e->entries->first()?->attach('flag', true),
        );

        $entry = $this->lookup($dispatcher, $tagger)->lookup(1);

        self::assertSame('/r/1', $entry->to);
        self::assertTrue($entry->flag);
        self::assertSame(['tl_test.1'], $tagger->tagged);
    }

    public function testOnlyMembersAreTaggedAndAnIdenticalLookupTagsNoMore(): void
    {
        $lookup = $this->lookup(tagger: $tagger);

        $lookup->lookup([2, 4, 1]);
        $lookup->lookup(['2', 4, 1]);

        self::assertSame(['tl_test.2', 'tl_test.1'], $tagger->tagged);
    }

    public function testLookupInPhaseOneDoesNotRecurseAndChainsOnlyWhenRead(): void
    {
        // The same listener serves every collection, the lookup's result included; the batch lookup runs in phase 1.
        // A closure, not an arrow function: an arrow function would capture $phase1 by value.
        $phase1 = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListDriverEntriesEvent::class,
            static function (ListDriverEntriesEvent $e) use (&$phase1): void {
                $e->attach(
                    'similar',
                    static function (EntryCollection $all, EntryLookupInterface $l) use (&$phase1): \Closure {
                        ++$phase1;
                        $next = static fn (mixed $id): int => ((int) $id % 3) + 1;
                        $similar = $l->lookup(\array_map($next, $all->column('id')));

                        return static function (Entry $x) use ($similar, $next): ?Entry {
                            foreach ($similar as $s) {
                                if ((int) $s->row['id'] === $next($x->row['id'])) {
                                    return $s;
                                }
                            }

                            return null;
                        };
                    },
                );
            },
        );

        $entry = $this->lookup($dispatcher)->lookup(1);
        self::assertSame([1, 0], [$this->queries, $phase1], 'Dispatching ran no phase 1.');

        self::assertSame(2, (int) $entry->similar->row['id']);
        self::assertSame([2, 1], [$this->queries, $phase1], "The result's own event registered lazily.");

        self::assertSame(3, (int) $entry->similar->similar->row['id']);
        self::assertSame([3, 2], [$this->queries, $phase1]);
    }

    private function lookup(?EventDispatcher $dispatcher = null, ?SpyTagger &$tagger = null): EntryLookup
    {
        $dispatcher ??= new EventDispatcher();
        $connection = $this->createConnection([
            ['id' => 1, 'published' => 1],
            ['id' => 2, 'published' => 1],
            ['id' => 3, 'published' => 1],
            ['id' => 4, 'published' => 0],
        ]);
        $list = $this->createList(self::publishedOnly());
        $intent = new ValidationIntent();
        $loader = $this->createLoaderFactory($connection, $dispatcher)
            ->createValidationLoader(new ValidationLoaderConfig($list, $intent, 'id'));
        $tagger = new SpyTagger();

        return new EntryLookup(
            $loader,
            new EntryCollector($dispatcher, $list, $intent, static fn (Entry $e): string => '/r/' . $e->row['id']),
            $tagger,
        );
    }

    /**
     * @return list<int> the row ids of the entries
     */
    private static function ids(mixed $entries): array
    {
        self::assertInstanceOf(EntryCollection::class, $entries);

        return \array_map(static fn (Entry $e): int => (int) $e->row['id'], \iterator_to_array($entries));
    }
}
