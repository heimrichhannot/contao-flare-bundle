<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Migration;

use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

/**
 * A fake `tl_flare_filter` behind a mocked DBAL connection, shared by the migration tests: `$this->columns` is its
 * schema, `$this->rows` its content. It serves the statements of every migration-managed marker column alike, so
 * several migrations can run on one table.
 *
 * @mixin TestCase
 */
trait FakeFilterTableTrait
{
    private const FAKE_TABLE = 'tl_flare_filter';

    /** @var list<string>|null The table's columns, or null if the table does not exist. */
    private ?array $columns = [];

    /** @var array<string, string> The current default of each marker column, by column. */
    private array $markerDefaults = [];

    /** @var array<int, array<string, mixed>> The table's rows by id; `update()` calls are applied to them. */
    private array $rows = [];

    /** @var list<array{array<string, mixed>, array<string, mixed>}> The recorded `update()` calls: data, criteria. */
    private array $updates = [];

    /** @var list<string> The recorded `executeStatement()` calls. */
    private array $statements = [];

    /** @var array<int, array<string, mixed>> The `tl_flare_list` rows by id. */
    private array $lists = [];

    /** @var list<string> The data containers loaded through the framework. */
    private array $loadedDcas = [];

    /**
     * Resets the fake to an empty table with the given columns.
     *
     * @param list<string> $columns
     */
    private function setUpFakeTable(array $columns): void
    {
        $this->columns = $columns;
        $this->markerDefaults = [];
        $this->rows = [];
        $this->updates = [];
        $this->statements = [];
        $this->lists = [];
        $this->loadedDcas = [];
    }

    /**
     * Adds a row. A column of the schema the defaults below do not cover is a marker column: the row gets the column's
     * current default ('0' unless a statement has switched it).
     *
     * @param array<string, mixed> $values
     */
    private function addRow(int $id, string $type, array $values): void
    {
        $row = [
            'id' => (string) $id,
            'pid' => '1',
            'type' => $type,
            'intrinsic' => '0',
            'boolMode' => '',
            'preselect' => null,
            'prefill' => null,
            'control' => '',
            'value' => null,
            'ctl_boolBinaryChoices' => '',
            'ctl_isMandatory' => '0',
            'ctl_isMultiple' => '0',
            'ctl_isExpanded' => '0',
            'ctl_hasEmptyOption' => '0',
            'ctl_formatEmptyOption' => '',
            'ctl_formatEmptyOptionCustom' => '',
            'ctl_placeholder' => '',
            'elm_whitelistParents' => null,
            'elm_groupWhitelistParents' => null,
            'title' => '',
            'ctl_label' => '',
            'elm_isLimited' => '',
            'elm_configureStart' => '',
            'elm_configureStop' => '',
            'elm_startAt' => '',
            'elm_stopAt' => '',
            'exclusive' => '0',
            'outputs' => null,
        ];

        foreach ($this->columns ?? [] as $column)
        {
            if (!\array_key_exists($column, $row)) {
                $row[$column] = $this->markerDefaults[$column] ?? '0';
            }
        }

        $this->rows[$id] = \array_merge($row, $values);
    }

    /**
     * The per-row `update()` calls.
     *
     * @return list<array{array<string, mixed>, array<string, mixed>}>
     */
    private function rowUpdates(): array
    {
        return \array_values(\array_filter(
            $this->updates,
            static fn (array $update): bool => isset($update[1]['id']),
        ));
    }

    /**
     * A connection to the fake table. Statements it does not serve fail the test.
     */
    private function fakeConnection(): Connection
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->with([self::FAKE_TABLE])->willReturnCallback(
            fn (): bool => $this->columns !== null,
        );
        $schemaManager->method('introspectTable')->with(self::FAKE_TABLE)->willReturnCallback(
            fn (): Table => new Table(self::FAKE_TABLE, \array_map(
                static fn (string $name): Column => new Column($name, Type::getType(Types::STRING)),
                $this->columns ?? [],
            )),
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchAllAssociative')->willReturnCallback(function (string $sql): array {
            if (!\preg_match("/^SELECT \\* FROM tl_flare_filter WHERE (\\w+) = '0' ORDER BY id$/", $sql, $match)) {
                self::fail('Unexpected query: ' . $sql);
            }

            return \array_values(\array_filter(
                $this->rows,
                static fn (array $row): bool => $row[$match[1]] === '0',
            ));
        });
        $connection->method('update')->willReturnCallback(function (string $table, array $data, array $criteria): int {
            self::assertSame(self::FAKE_TABLE, $table);

            $this->updates[] = [$data, $criteria];
            $affected = 0;

            foreach ($this->rows as $id => $row)
            {
                if (\array_intersect_key($row, $criteria) == $criteria) {
                    $this->rows[$id] = \array_merge($row, $data);
                    ++$affected;
                }
            }

            return $affected;
        });
        $connection->method('fetchOne')->willReturnCallback(function (string $sql): int|false {
            self::assertSame("SELECT 1 FROM tl_flare_filter WHERE control <> '' OR value <> '' LIMIT 1", $sql);

            foreach ($this->rows as $row)
            {
                if ($row['control'] !== '' || ($row['value'] !== null && $row['value'] !== '')) {
                    return 1;
                }
            }

            return false;
        });
        $connection->method('fetchAssociative')->willReturnCallback(function (string $sql, array $params): array|false {
            self::assertSame('SELECT * FROM tl_flare_list WHERE id = ?', $sql);

            return $this->lists[$params[0]] ?? false;
        });
        $connection->method('executeStatement')->willReturnCallback(function (string $sql): int {
            $this->statements[] = $sql;

            $add = "/^ALTER TABLE tl_flare_filter ADD (\\w+) CHAR\\(1\\) NOT NULL DEFAULT '0'$/";

            if (\preg_match($add, $sql, $match))
            {
                $this->columns[] = $match[1];
                $this->markerDefaults[$match[1]] = '0';

                foreach ($this->rows as $id => $row) {
                    $this->rows[$id][$match[1]] = '0';
                }
            }
            elseif (\preg_match("/^ALTER TABLE tl_flare_filter ALTER (\\w+) SET DEFAULT '1'$/", $sql, $match)) {
                $this->markerDefaults[$match[1]] = '1';
            }
            else {
                self::fail('Unexpected statement: ' . $sql);
            }

            return 0;
        });

        return $connection;
    }

    /**
     * A framework whose `Controller` adapter records the data containers loaded through it.
     */
    private function fakeFramework(): ContaoFramework
    {
        $controller = $this->getMockBuilder(Adapter::class)
            ->disableOriginalConstructor()
            ->addMethods(['loadDataContainer'])
            ->getMock();
        $controller->method('loadDataContainer')->willReturnCallback(function (string $table): void {
            $this->loadedDcas[] = $table;
        });

        $framework = $this->createMock(ContaoFramework::class);
        $framework->method('getAdapter')->with(Controller::class)->willReturn($controller);

        return $framework;
    }
}
