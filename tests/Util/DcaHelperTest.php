<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Util;

use HeimrichHannot\FlareBundle\Tests\Filter\Element\StubsContaoDcaTrait;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\SqlColumn;
use HeimrichHannot\FlareBundle\Util\SqlColumnKind;
use PHPUnit\Framework\TestCase;

class DcaHelperTest extends TestCase
{
    use StubsContaoDcaTrait;

    /**
     * @dataProvider provideSqlDefinitions
     */
    public function testClassifiesSqlDefinitions(array|string|null $sql, ?SqlColumnKind $expected): void
    {
        self::assertSame($expected, DcaHelper::classifySql($sql));
    }

    public static function provideSqlDefinitions(): iterable
    {
        $integer = [
            'bigint(20) NULL',
            'int(10) unsigned NOT NULL default 0',
            'smallint(5) unsigned NOT NULL default 0',
            ' INT(10) NULL',
            'integer',
            ['type' => 'boolean', 'default' => false],
            ['type' => 'integer'],
            ['type' => 'bigint'],
        ];
        $date = [
            'date NULL',
            'datetime NOT NULL',
            'timestamp NULL',
            'time NULL',
            'year(4) NULL',
            ['type' => 'date'],
            ['type' => 'date_immutable'],
            ['type' => 'datetime_immutable'],
            ['type' => 'datetimetz'],
            ['type' => 'time_immutable'],
            ['type' => 'string', 'columnDefinition' => 'DATE NULL'],
        ];
        $other = [
            'varchar(10) NULL',
            "char(1) NOT NULL default ''",
            'decimal(10,2)',
            ['type' => 'string', 'length' => 10],
            ['type' => 'dateinterval'],
            ['type' => 'date', 'columnDefinition' => 'varchar(10) NULL'],
        ];

        foreach ($integer as $i => $sql) {
            yield "integer $i" => [$sql, SqlColumnKind::Integer];
        }
        foreach ($date as $i => $sql) {
            yield "date $i" => [$sql, SqlColumnKind::Date];
        }
        foreach ($other as $i => $sql) {
            yield "other $i" => [$sql, SqlColumnKind::Other];
        }

        yield 'null' => [null, null];
        yield 'empty string' => ['', null];
        yield 'empty array' => [[], null];
    }

    public function testGetColumnsListsColumnsWithSql(): void
    {
        $this->stubTestTable();

        self::assertEquals(
            [
                new SqlColumn('start', 'start', SqlColumnKind::Integer),
                new SqlColumn('startTime', 'begins', SqlColumnKind::Integer),
            ],
            DcaHelper::getColumns('tl_test'),
        );
    }

    public function testGetColumnFindsThePhysicalName(): void
    {
        $this->stubTestTable();

        self::assertEquals(
            new SqlColumn('startTime', 'begins', SqlColumnKind::Integer),
            DcaHelper::getColumn('tl_test', 'STARTTIME'),
        );
        self::assertNull(DcaHelper::getColumn('tl_test', 'begins'));
    }

    public function testNoTableHasNoColumns(): void
    {
        self::assertSame([], DcaHelper::getColumns(''));
        self::assertSame([], DcaHelper::getColumns(null));
        self::assertNull(DcaHelper::getColumn('', 'start'));
    }

    private function stubTestTable(): void
    {
        $this->stubDca('tl_test', [
            'fields' => [
                'start' => ['sql' => 'bigint(20) NULL'],
                'begins' => ['sql' => ['type' => 'integer', 'name' => 'startTime']],
                'virtual' => [],
                'bad' => ['sql' => ['type' => 'integer', 'name' => 'event-start']],
            ],
        ]);
    }
}
