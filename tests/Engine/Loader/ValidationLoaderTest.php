<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoader;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
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
     * A loader with the auto-item field $field over a list on `tl_test` with $rows.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function loader(array $rows, string $field = 'alias', ?Connection $connection = null): ValidationLoader
    {
        return $this->createLoaderFactory($connection ?? $this->createConnection($rows), new EventDispatcher())
            ->createValidationLoader(new ValidationLoaderConfig(
                list: $this->createList(),
                intent: new ValidationIntent(autoItemField: $field),
                autoItemField: $field,
            ));
    }
}
