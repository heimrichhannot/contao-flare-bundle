<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoader;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ValidationLoaderTest extends TestCase
{
    use EnginePipelineTrait;

    public function testTheAutoItemZeroIsLookedUp(): void
    {
        // The reader URL generator links an auto-item '0'; the reader must find it.
        $row = $this->loader([['id' => 1, 'alias' => '0']])->fetchEntryByAutoItem('0');

        self::assertSame(1, (int) ($row['id'] ?? 0));
    }

    public function testAnEmptyAutoItemIsAMissWithoutAQuery(): void
    {
        self::assertNull($this->loader([['id' => 1, 'alias' => '']])->fetchEntryByAutoItem(''));
        self::assertSame(0, $this->queries);
    }

    /**
     * The database matches more than the stored value — SQLite casts a numeric string for an INTEGER column like MySQL
     * does — but only the canonical auto-item finds the entry: no duplicate URLs.
     *
     * @dataProvider provideNonCanonicalIds
     */
    public function testANonCanonicalIdIsAMiss(string $autoItem): void
    {
        $loader = $this->loader([['id' => 12, 'alias' => 'a']], 'id');

        self::assertSame(12, (int) ($loader->fetchEntryByAutoItem('12')['id'] ?? 0));
        self::assertNull($loader->fetchEntryByAutoItem($autoItem));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNonCanonicalIds(): iterable
    {
        yield 'a leading zero' => ['012'];
        yield 'a decimal' => ['12.0'];
        yield 'an exponent' => ['1.2e1'];
    }

    public function testAnAliasInAnotherCaseIsAMiss(): void
    {
        // a case-insensitive column, like MySQL's default collations
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE tl_test (id INTEGER PRIMARY KEY, alias TEXT COLLATE NOCASE, published INTEGER DEFAULT 0)',
        );
        $connection->insert('tl_test', ['id' => 1, 'alias' => 'visible']);

        $loader = $this->loader([], connection: $connection);

        self::assertSame(1, (int) ($loader->fetchEntryByAutoItem('visible')['id'] ?? 0));
        self::assertNull($loader->fetchEntryByAutoItem('VISIBLE'));
    }

    /**
     * A case-insensitive column matches both rows; each auto-item finds the row that holds it exactly.
     *
     * @param list<array{int, string}> $rows
     *
     * @dataProvider provideCaseVariants
     */
    public function testEachCaseVariantFindsItsOwnRow(array $rows): void
    {
        $loader = $this->loader([], connection: $this->collatedConnection('NOCASE', $rows));

        self::assertSame(1, (int) ($loader->fetchEntryByAutoItem('Foo')['id'] ?? 0));
        self::assertSame(2, (int) ($loader->fetchEntryByAutoItem('foo')['id'] ?? 0));
        self::assertNull($loader->fetchEntryByAutoItem('FOO'));
    }

    /**
     * @return iterable<string, array{list<array{int, string}>}>
     */
    public static function provideCaseVariants(): iterable
    {
        yield 'Foo first' => [[[1, 'Foo'], [2, 'foo']]];
        yield 'foo first' => [[[2, 'foo'], [1, 'Foo']]];
    }

    /**
     * A column that ignores trailing spaces matches both rows; each auto-item finds the row that holds it exactly.
     *
     * @param list<array{int, string}> $rows
     *
     * @dataProvider provideSpaceVariants
     */
    public function testEachTrailingSpaceVariantFindsItsOwnRow(array $rows): void
    {
        $loader = $this->loader([], connection: $this->collatedConnection('RTRIM', $rows));

        self::assertSame(1, (int) ($loader->fetchEntryByAutoItem('a')['id'] ?? 0));
        self::assertSame(2, (int) ($loader->fetchEntryByAutoItem('a ')['id'] ?? 0));
    }

    /**
     * @return iterable<string, array{list<array{int, string}>}>
     */
    public static function provideSpaceVariants(): iterable
    {
        yield 'a first' => [[[1, 'a'], [2, 'a ']]];
        yield 'a space first' => [[[2, 'a '], [1, 'a']]];
    }

    /**
     * Without the grouping by id SQLite scans in insertion order (`id` is no primary key here): only the tie-breaker
     * `ORDER BY main.id` makes the lowest id win.
     *
     * @param list<array{int, string}> $rows
     *
     * @dataProvider provideEqualRows
     */
    public function testOfStrictlyEqualRowsTheLowestIdWins(array $rows): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ModifyListQueryStructEvent::class,
            static function (ModifyListQueryStructEvent $event): void {
                $event->queryStruct->setGroupBy(null);
            },
        );

        $loader = $this->loader([], connection: $this->collatedConnection('BINARY', $rows), dispatcher: $dispatcher);

        self::assertSame(1, (int) ($loader->fetchEntryByAutoItem('x')['id'] ?? 0));
    }

    /**
     * @return iterable<string, array{list<array{int, string}>}>
     */
    public static function provideEqualRows(): iterable
    {
        yield 'id 2 first' => [[[2, 'x'], [1, 'x']]];
        yield 'id 1 first' => [[[1, 'x'], [2, 'x']]];
    }

    public function testTheListsOrderGoesBeforeTheIdTieBreaker(): void
    {
        $connection = $this->collatedConnection('BINARY', []);
        $connection->insert('tl_test', ['id' => 1, 'alias' => 'x', 'title' => 'a']);
        $connection->insert('tl_test', ['id' => 2, 'alias' => 'x', 'title' => 'b']);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ModifyListQueryStructEvent::class,
            static function (ModifyListQueryStructEvent $event): void {
                $event->queryStruct->setOrderBy([['main.title', 'DESC']]);
            },
        );

        $loader = $this->loader([], connection: $connection, dispatcher: $dispatcher);

        self::assertSame(2, (int) ($loader->fetchEntryByAutoItem('x')['id'] ?? 0));
    }

    public function testALimitAndOffsetSetByAListenerAreReset(): void
    {
        // priority 0: after the bundle's PageModifierListener
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ModifyListQueryStructEvent::class,
            static function (ModifyListQueryStructEvent $event): void {
                $event->queryStruct->setLimit(1)->setOffset(1);
            },
        );

        $connection = $this->collatedConnection('NOCASE', [[1, 'Foo'], [2, 'foo']]);
        $loader = $this->loader([], connection: $connection, dispatcher: $dispatcher);

        self::assertSame(1, (int) ($loader->fetchEntryByAutoItem('Foo')['id'] ?? 0));
        self::assertSame(2, (int) ($loader->fetchEntryByAutoItem('foo')['id'] ?? 0));
    }

    public function testAStrictMatchBehindSeveralCollationMatchesIsFound(): void
    {
        $connection = $this->collatedConnection('NOCASE', [[1, 'FOO'], [2, 'Foo'], [3, 'foo']]);

        self::assertSame(3, (int) ($this->loader([], connection: $connection)->fetchEntryByAutoItem('foo')['id'] ?? 0));
    }

    /**
     * SQLite in memory with the table `tl_test` whose `alias` column compares by $collation, filled with $rows
     * (`[id, alias]`) in the given order. `id` is no primary key: an unordered, ungrouped scan returns insertion order.
     *
     * @param list<array{int, string}> $rows
     */
    private function collatedConnection(string $collation, array $rows): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            "CREATE TABLE tl_test (id INTEGER, alias TEXT COLLATE {$collation}, title TEXT DEFAULT '', "
            . 'published INTEGER DEFAULT 0)',
        );

        foreach ($rows as [$id, $alias]) {
            $connection->insert('tl_test', ['id' => $id, 'alias' => $alias]);
        }

        return $connection;
    }

    /**
     * A loader with the auto-item field $field over a list on `tl_test` with $rows.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function loader(
        array            $rows,
        string           $field = 'alias',
        ?Connection      $connection = null,
        ?EventDispatcher $dispatcher = null,
    ): ValidationLoader {
        return $this->createLoaderFactory(
            $connection ?? $this->createConnection($rows),
            $dispatcher ?? new EventDispatcher(),
        )
            ->createValidationLoader(new ValidationLoaderConfig(
                list: $this->createList(),
                intent: new ValidationIntent(autoItemField: $field),
                autoItemField: $field,
            ));
    }
}
