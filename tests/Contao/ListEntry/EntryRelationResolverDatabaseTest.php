<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\ListEntry;

use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DC_Table;
use Contao\DcaExtractor;
use Contao\Model;
use Contao\Model\Collection;
use Contao\Model\Registry;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryRelationResolver;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\Tests\Filter\Element\StubsContaoDcaTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\RelationSourceModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\RelationTargetModelFixture;
use Symfony\Component\DependencyInjection\Container;

/**
 * The resolver against Contao's own `Model::getRelated()` on a SQLite database: Contao's real models, registry,
 * DCA extract and finders, only the database and the DCA are the test's.
 */
class EntryRelationResolverDatabaseTest extends ContaoTestCase
{
    use StubsContaoDcaTrait;

    /** Contao's static state that would otherwise leak between the test methods (and into other tests). */
    private const STATIC_STATE = [
        Database::class,
        DcaExtractor::class,
        Registry::class,
        [Model::class, ['arrColumnCastTypes']],
    ];

    private Connection $connection;
    private EntryRelationResolver $resolver;
    private mixed $previousModels;
    private mixed $previousLanguage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetStaticProperties(self::STATIC_STATE);

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->getNativeConnection()->sqliteCreateFunction(
            'FIND_IN_SET',
            static function (mixed $needle, mixed $list): int {
                $position = \array_search((string) $needle, \explode(',', (string) $list), true);

                return false === $position ? 0 : $position + 1;
            },
            2,
        );

        $this->connection->executeStatement(
            'CREATE TABLE tl_flare_rel_source (id INTEGER PRIMARY KEY, pid INTEGER, tags TEXT)',
        );
        $this->connection->executeStatement('CREATE TABLE tl_flare_rel_target (id INTEGER PRIMARY KEY, title TEXT)');
        $this->connection->insert('tl_flare_rel_target', ['id' => 1, 'title' => 'a']);
        $this->connection->insert('tl_flare_rel_target', ['id' => 2, 'title' => 'b']);
        $this->connection->insert('tl_flare_rel_target', ['id' => 3, 'title' => 'c']);
        $this->connection->insert('tl_flare_rel_source', ['id' => 5, 'pid' => 2, 'tags' => \serialize(['3', '1'])]);

        $this->stubDca('tl_flare_rel_source', [
            'config' => ['dataContainer' => DC_Table::class],
            'fields' => [
                'pid' => [
                    'relation' => ['type' => 'belongsTo', 'load' => 'lazy', 'table' => 'tl_flare_rel_target'],
                ],
                'tags' => [
                    'relation' => ['type' => 'belongsToMany', 'load' => 'lazy', 'table' => 'tl_flare_rel_target'],
                ],
            ],
        ]);
        $this->stubDca('tl_flare_rel_target', [
            'config' => ['dataContainer' => DC_Table::class],
            'fields' => ['title' => []],
        ]);

        $container = new Container();
        $container->set(Config::class, $this->createMock(Config::class));
        $container->set('database_connection', $this->connection);
        $container->setParameter('kernel.cache_dir', __DIR__ . '/no-such-cache-dir');
        $container->setParameter('kernel.debug', true);
        System::setContainer($container);

        $this->previousModels = $GLOBALS['TL_MODELS'] ?? null;
        $this->previousLanguage = $GLOBALS['TL_LANG'] ?? null;
        $GLOBALS['TL_MODELS']['tl_flare_rel_source'] = RelationSourceModelFixture::class;
        $GLOBALS['TL_MODELS']['tl_flare_rel_target'] = RelationTargetModelFixture::class;
        $GLOBALS['TL_LANG']['MSC'] ??= ['flare' => 'stub'];

        $framework = $this->createMock(ContaoFramework::class);
        $framework->method('getAdapter')->willReturnCallback(static fn (string $class): Adapter => new Adapter($class));

        $this->resolver = new EntryRelationResolver($framework);
    }

    protected function tearDown(): void
    {
        $this->restoreGlobal('TL_MODELS', $this->previousModels);
        $this->restoreGlobal('TL_LANG', $this->previousLanguage);

        $this->resetStaticProperties(self::STATIC_STATE);

        parent::tearDown();
    }

    public function testToOneEqualsGetRelated(): void
    {
        // data of this test alone: a Contao\Database left over from another test would not see it
        $this->connection->update('tl_flare_rel_target', ['title' => 'to-one'], ['id' => 2]);

        $expected = RelationSourceModelFixture::findById(5)?->getRelated('pid');
        Registry::getInstance()->reset();

        $actual = $this->resolver->resolve($this->entry(), 'pid');

        self::assertInstanceOf(RelationTargetModelFixture::class, $expected);
        self::assertInstanceOf(RelationTargetModelFixture::class, $actual);
        self::assertSame($expected->row(), $actual->row());
        self::assertSame('to-one', $actual->title);
    }

    public function testToManyEqualsGetRelatedInTheStoredOrder(): void
    {
        // data of this test alone, as above
        $this->connection->update('tl_flare_rel_target', ['title' => 'to-many'], ['id' => 3]);

        $expected = RelationSourceModelFixture::findById(5)?->getRelated('tags');
        Registry::getInstance()->reset();

        $actual = $this->resolver->resolve($this->entry(), 'tags');

        self::assertInstanceOf(Collection::class, $expected);
        self::assertInstanceOf(Collection::class, $actual);

        $rows = self::rows($actual);

        self::assertSame(self::rows($expected), $rows);
        self::assertSame([3, 1], \array_column($rows, 'id'));
        self::assertSame(['to-many', 'a'], \array_column($rows, 'title'));
    }

    private function entry(): Entry
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM tl_flare_rel_source WHERE id = 5');
        self::assertIsArray($row);

        $entry = EntryCollection::forTable('tl_flare_rel_source', [$row])->first();
        self::assertNotNull($entry);

        return $entry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(Collection $collection): array
    {
        return \array_map(static fn (Model $model): array => $model->row(), $collection->getModels());
    }

    private function restoreGlobal(string $name, mixed $previous): void
    {
        if (null === $previous) {
            unset($GLOBALS[$name]);

            return;
        }

        $GLOBALS[$name] = $previous;
    }
}
