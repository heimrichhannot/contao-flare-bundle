<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use HeimrichHannot\FlareBundle\Migration\RenameFilterColumnsMigration;
use PHPUnit\Framework\TestCase;

final class RenameFilterColumnsMigrationTest extends TestCase
{
    private const TABLE = 'tl_flare_filter';

    /** @var list<string> */
    private array $statements = [];

    protected function setUp(): void
    {
        $this->statements = [];
    }

    public function testShouldRunOnlyWhileOldColumnsRemain(): void
    {
        self::assertFalse($this->migration(null)->shouldRun(), 'no table');

        self::assertTrue($this->migration([self::string('fieldGeneric')])->shouldRun());
        self::assertTrue($this->migration([self::string('formAlias')])->shouldRun());

        // Column names are case-insensitive: an existing new column, in any case, settles its pair.
        self::assertFalse($this->migration([
            self::string('fieldGeneric'),
            self::string('ELM_FIELDGENERIC'),
        ])->shouldRun());

        self::assertFalse($this->migration([
            self::string('elm_fieldGeneric'),
            self::string('ctl_label'),
            self::string('alias'),
        ])->shouldRun(), 'already renamed');

        self::assertTrue($this->migration([
            self::string('elm_fieldGeneric'),
            self::string('label'),
        ])->shouldRun(), 'partially renamed');
    }

    /**
     * @dataProvider platforms
     */
    public function testRunChangesColumnsKeepingTheirDefinition(AbstractPlatform $platform): void
    {
        $columns = [
            'fieldGeneric' => self::string('fieldGeneric', 255, ['collation' => 'utf8mb4_unicode_ci']),
            'isMultiple' => new Column('isMultiple', Type::getType(Types::BOOLEAN), ['default' => 0]),
            'whitelistParents' => new Column('whitelistParents', Type::getType(Types::BLOB), ['notnull' => false]),
            'formAlias' => self::string('formAlias', 128),
            // Already renamed: the old column stays untouched (migrations never delete).
            'columnsGeneric' => new Column('columnsGeneric', Type::getType(Types::BLOB), ['notnull' => false]),
            'elm_columnsGeneric' => new Column('elm_columnsGeneric', Type::getType(Types::BLOB), ['notnull' => false]),
            'title' => self::string('title'),
        ];

        $migration = $this->migration(\array_values($columns), $platform);

        $result = $migration->run();

        self::assertTrue($result->isSuccessful());

        $expected = [];

        foreach (['fieldGeneric' => 'elm_fieldGeneric', 'whitelistParents' => 'elm_whitelistParents',
                     'isMultiple' => 'ctl_isMultiple', 'formAlias' => 'alias'] as $old => $new)
        {
            $expected[] = \sprintf(
                'ALTER TABLE tl_flare_filter CHANGE `%s` %s',
                $old,
                $platform->getColumnDeclarationSQL('`' . $new . '`', $columns[$old]->toArray()),
            );
        }

        self::assertSame($expected, $this->statements);
        self::assertSame(
            'ALTER TABLE tl_flare_filter CHANGE `fieldGeneric` `elm_fieldGeneric` VARCHAR(255) DEFAULT \'\' NOT NULL'
            . ' COLLATE `utf8mb4_unicode_ci`',
            $this->statements[0],
        );
    }

    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function platforms(): iterable
    {
        yield 'mysql' => [new MySQLPlatform()];
        yield 'mariadb' => [new MariaDBPlatform()];
    }

    public function testFormVariantIsNotRenamed(): void
    {
        self::assertArrayNotHasKey('formVariant', RenameFilterColumnsMigration::COLUMNS);
        self::assertNotContains('control', RenameFilterColumnsMigration::COLUMNS);

        $migration = $this->migration([self::string('formVariant', 128), self::string('title')]);

        self::assertFalse($migration->shouldRun());

        $migration->run();

        self::assertSame([], $this->statements);
    }

    public function testColumnMapIsFrozen(): void
    {
        $elm = [
            'fieldGeneric', 'columnsGeneric', 'usePublished', 'fieldPublished', 'invertPublished', 'useStart',
            'fieldStart', 'useStop', 'fieldStop', 'whichPtable', 'fieldPid', 'fieldPtable', 'tablePtable',
            'whitelistParents', 'groupWhitelistParents', 'useWhitelistForOptionsOnly', 'equationLeft',
            'equationOperator', 'equationRight', 'configureStart', 'startAt', 'configureStop', 'stopAt',
            'hasExtendedEvents', 'isLimited',
        ];
        $ctl = [
            'label', 'placeholder', 'isMandatory', 'isMultiple', 'isExpanded', 'hasEmptyOption', 'formatLabel',
            'formatLabelCustom', 'formatEmptyOption', 'formatEmptyOptionCustom', 'boolBinaryChoices',
        ];

        $expected = \array_merge(
            \array_combine($elm, \array_map(static fn (string $c): string => 'elm_' . $c, $elm)),
            \array_combine($ctl, \array_map(static fn (string $c): string => 'ctl_' . $c, $ctl)),
            ['formAlias' => 'alias'],
        );

        self::assertSame($expected, RenameFilterColumnsMigration::COLUMNS);
    }

    /**
     * @param list<Column>|null $columns The table's columns, or null if the table does not exist.
     */
    private function migration(?array $columns, ?AbstractPlatform $platform = null): RenameFilterColumnsMigration
    {
        $platform ??= new MySQLPlatform();

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->with([self::TABLE])->willReturn($columns !== null);
        $schemaManager->method('introspectTable')->with(self::TABLE)
            ->willReturnCallback(static fn (): Table => new Table(self::TABLE, $columns ?? []));

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('quoteIdentifier')->willReturnCallback($platform->quoteIdentifier(...));
        $connection->method('executeStatement')->willReturnCallback(function (string $sql): int {
            $this->statements[] = $sql;

            return 0;
        });

        return new RenameFilterColumnsMigration($connection);
    }

    /**
     * @param array<string, string> $platformOptions
     */
    private static function string(string $name, int $length = 255, array $platformOptions = []): Column
    {
        return new Column($name, Type::getType(Types::STRING), [
            'length' => $length,
            'notnull' => true,
            'default' => '',
            'platformOptions' => $platformOptions,
        ]);
    }
}
