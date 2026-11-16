<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\ListEntry;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DcaExtractor;
use Contao\FilesModel;
use Contao\Model;
use Contao\Model\Collection;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryRelationResolver;
use HeimrichHannot\FlareBundle\Exception\MissingModelRefError;
use HeimrichHannot\FlareBundle\Exception\UndefinedRelationError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\Tests\Fixture\TestModelFixture;
use PHPUnit\Framework\MockObject\MockObject;

class EntryRelationResolverTest extends ContaoTestCase
{
    private const RELATIONS = [
        'pid' => ['type' => 'belongsTo', 'table' => 'tl_archive', 'field' => 'id'],
        'author' => ['type' => 'hasOne', 'table' => 'tl_user', 'field' => 'id'],
        'parent' => ['type' => 'belongsTo', 'table' => 'tl_test', 'field' => 'id'],
        'category' => ['type' => 'belongsTo', 'table' => 'tl_archive', 'field' => 'alias'],
        'tags' => ['type' => 'belongsToMany', 'table' => 'tl_tag', 'field' => 'id'],
        'children' => ['type' => 'hasMany', 'table' => 'tl_tag', 'field' => 'id'],
        'csv' => ['type' => 'belongsToMany', 'table' => 'tl_tag', 'field' => 'id', 'delimiter' => ','],
        'ordered' => ['type' => 'belongsToMany', 'table' => 'tl_tag', 'field' => 'order'],
        'files' => ['type' => 'belongsToMany', 'table' => 'tl_files', 'field' => 'uuid'],
        'image' => ['type' => 'belongsTo', 'table' => 'tl_nomodel', 'field' => 'id'],
        'odd' => ['type' => 'manyToMany', 'table' => 'tl_tag', 'field' => 'id'],
        'oddNoModel' => ['type' => 'manyToMany', 'table' => 'tl_nomodel', 'field' => 'id'],
        'untyped' => ['table' => 'tl_tag', 'field' => 'id'],
    ];

    /** `Model::getClassFromTable()` */
    private Adapter&MockObject $models;

    /** The finders of the related class, {@see TestModelFixture} */
    private Adapter&MockObject $finder;

    /** `FilesModel::findMultipleByUuids()` */
    private Adapter&MockObject $files;

    /** The connection that quotes the target field */
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = $this->mockAdapter(['getClassFromTable']);
        $this->finder = $this->mockAdapter(['findOneBy', 'findBy']);
        $this->files = $this->mockAdapter(['findMultipleByUuids']);
        $this->connection = $this->createMock(Connection::class);
        // like DBAL: each part of a qualified name is quoted
        $this->connection->method('quoteIdentifier')->willReturnCallback(
            static fn (string $name): string => '`' . \str_replace('.', '`.`', $name) . '`',
        );
    }

    /**
     * @dataProvider provideToOne
     */
    public function testToOneFindsTheRelatedRecordByItsField(
        string $column,
        string $table,
        string $field,
        mixed $value,
    ): void {
        $related = new TestModelFixture(['id' => 1]);
        $this->models
            ->expects(self::once())
            ->method('getClassFromTable')
            ->with(self::identicalTo($table))
            ->willReturn(TestModelFixture::class);
        $this->finder
            ->expects(self::once())
            ->method('findOneBy')
            ->with(self::identicalTo($field), self::identicalTo($value))
            ->willReturn($related);
        $this->finder->expects(self::never())->method('findBy');

        self::assertSame($related, $this->resolver()->resolve($this->entry(['id' => 5, $column => $value]), $column));
    }

    /**
     * @return iterable<string, array{string, string, string, mixed}>
     */
    public static function provideToOne(): iterable
    {
        yield 'belongsTo' => ['pid', 'tl_archive', 'id', 7];
        yield 'hasOne' => ['author', 'tl_user', 'id', 3];
        yield "into the list's own table" => ['parent', 'tl_test', 'id', 2];
        yield 'a target field other than id' => ['category', 'tl_archive', 'alias', 'news'];
    }

    /**
     * @dataProvider provideToMany
     *
     * @param list<string> $values
     */
    public function testToManyFindsTheRecordsInTheStoredOrder(string $column, string $stored, array $values): void
    {
        $collection = new Collection([new TestModelFixture(['id' => 3])], 'tl_test');
        $field = '`tl_tag`.`id`';
        $placeholders = \implode(',', \array_fill(0, \count($values), '?'));
        $this->classes();
        $this->finder
            ->expects(self::once())
            ->method('findBy')
            ->with(
                self::identicalTo(["{$field} IN({$placeholders})"]),
                self::identicalTo([...$values, \implode(',', $values)]),
                self::identicalTo(['order' => "FIND_IN_SET({$field}, ?)"]),
            )
            ->willReturn($collection);
        $this->finder->expects(self::never())->method('findOneBy');
        $this->files->expects(self::never())->method('findMultipleByUuids');

        $entry = $this->entry(['id' => 5, $column => $stored]);

        self::assertSame($collection, $this->resolver()->resolve($entry, $column));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideToMany(): iterable
    {
        yield 'belongsToMany serialized' => ['tags', \serialize(['3', '1']), ['3', '1']];
        yield 'hasMany' => ['children', \serialize(['3', '1']), ['3', '1']];
        yield 'serialized with string keys' => ['tags', \serialize(['x' => '3', 'y' => '1']), ['3', '1']];
        yield 'with delimiter and spaces' => ['csv', '3, 1', ['3', '1']];
        yield 'a plain value' => ['tags', '5', ['5']];
        yield 'a quote and a backslash' => ['tags', \serialize(["o'k", 'a\\b']), ["o'k", 'a\\b']];
    }

    public function testTheTargetFieldIsQuotedThroughTheConnection(): void
    {
        $collection = new Collection([new TestModelFixture(['id' => 5])], 'tl_test');
        $this->classes();
        $this->connection->expects(self::once())->method('quoteIdentifier')->with(self::identicalTo('tl_tag.order'));
        $this->finder
            ->expects(self::once())
            ->method('findBy')
            ->with(
                self::identicalTo(['`tl_tag`.`order` IN(?)']),
                self::identicalTo(['5', '5']),
                self::identicalTo(['order' => 'FIND_IN_SET(`tl_tag`.`order`, ?)']),
            )
            ->willReturn($collection);

        $entry = $this->entry(['id' => 5, 'ordered' => \serialize(['5'])]);

        self::assertSame($collection, $this->resolver()->resolve($entry, 'ordered'));
    }

    /**
     * @dataProvider provideUuids
     *
     * @param list<string> $uuids
     */
    public function testFileUuidsGoToFilesModelWhateverClassIsRegistered(array $uuids): void
    {
        $collection = new Collection([new TestModelFixture(['id' => 9])], 'tl_test');
        $this->models
            ->expects(self::once())
            ->method('getClassFromTable')
            ->with(self::identicalTo('tl_files'))
            ->willReturn(TestModelFixture::class);
        $this->files
            ->expects(self::once())
            ->method('findMultipleByUuids')
            ->with(self::identicalTo($uuids))
            ->willReturn($collection);
        $this->finder->expects(self::never())->method('findBy');
        $this->finder->expects(self::never())->method('findOneBy');

        $entry = $this->entry(['id' => 5, 'files' => \serialize($uuids)]);

        self::assertSame($collection, $this->resolver()->resolve($entry, 'files'));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function provideUuids(): iterable
    {
        yield 'string' => [['9e474bae-ce18-11ef-8f8b-0242ac120002']];
        yield 'binary' => [[(string) \hex2bin('9e474baece1811ef8f8b0242ac120002')]];
    }

    /**
     * @dataProvider provideEmptyValues
     *
     * @param array<string, mixed> $row
     */
    public function testAnEmptyValueIsNullWithoutAQuery(string $column, array $row): void
    {
        $this->expectNoLookup();

        self::assertNull($this->resolver()->resolve($this->entry($row), $column));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function provideEmptyValues(): iterable
    {
        foreach (['pid', 'tags'] as $column) {
            yield "{$column} 0" => [$column, ['id' => 5, $column => 0]];
            yield "{$column} '0'" => [$column, ['id' => 5, $column => '0']];
            yield "{$column} ''" => [$column, ['id' => 5, $column => '']];
            yield "{$column} null" => [$column, ['id' => 5, $column => null]];
            yield "{$column} missing" => [$column, ['id' => 5]];
        }
    }

    public function testAnEmptySerializedArrayIsNullWithoutAQuery(): void
    {
        $this->classes();
        $this->finder->expects(self::never())->method('findBy');
        $this->finder->expects(self::never())->method('findOneBy');
        $this->files->expects(self::never())->method('findMultipleByUuids');

        self::assertNull($this->resolver()->resolve($this->entry(['id' => 5, 'tags' => 'a:0:{}']), 'tags'));
    }

    public function testAnEmptyValueIsNullBeforeTheRelatedClassOrTypeIsChecked(): void
    {
        $this->expectNoLookup();
        $resolver = $this->resolver();

        self::assertNull($resolver->resolve($this->entry(['id' => 5, 'image' => '']), 'image'));
        self::assertNull($resolver->resolve($this->entry(['id' => 5, 'odd' => 0]), 'odd'));
        self::assertNull($resolver->resolve($this->entry(['id' => 5, 'oddNoModel' => '0']), 'oddNoModel'));
    }

    public function testEachEntryAndColumnIsResolvedOnce(): void
    {
        $this->classes();
        $this->finder->expects(self::exactly(3))->method('findOneBy')->willReturn(null);
        $resolver = $this->resolver();
        // two entries of one record: the memo belongs to the entry, not to the record
        $first = $this->entry(['id' => 5, 'pid' => 7, 'parent' => 2]);
        $second = $this->entry(['id' => 5, 'pid' => 7, 'parent' => 2]);

        self::assertNull($resolver->resolve($first, 'pid'));
        self::assertNull($resolver->resolve($first, 'pid'));
        self::assertNull($resolver->resolve($first, 'parent'));
        self::assertNull($resolver->resolve($second, 'pid'));
        self::assertNull($resolver->resolve($second, 'pid'));
    }

    public function testWithoutModelRefThrows(): void
    {
        $this->expectNoLookup();

        $this->expectException(MissingModelRefError::class);
        $this->resolver()->resolve(new Entry(['id' => 1, 'pid' => 7]), 'pid');
    }

    public function testAColumnWithoutRelationThrows(): void
    {
        $this->expectNoLookup();

        $this->expectException(UndefinedRelationError::class);
        $this->expectExceptionMessage('The column "tl_test.title" has no relation.');
        $this->resolver()->resolve($this->entry(['id' => 5, 'title' => 'Foo']), 'title');
    }

    /**
     * @dataProvider provideMissingClasses
     */
    public function testARelatedTableWithoutModelClassThrows(string|\Throwable $lookup): void
    {
        $this->classes(['tl_nomodel' => $lookup]);
        $this->finder->expects(self::never())->method('findOneBy');

        $this->expectException(UndefinedRelationError::class);
        $this->expectExceptionMessage('The column "tl_test.image" relates to a table without a model class.');
        $this->resolver()->resolve($this->entry(['id' => 5, 'image' => 4]), 'image');
    }

    /**
     * @return iterable<string, array{string|\Throwable}>
     */
    public static function provideMissingClasses(): iterable
    {
        yield 'Contao 5 throws' => [new \RuntimeException('There is no class for table "tl_nomodel" registered.')];
        yield 'Contao 4.13 guesses' => ['Contao\NoSuchModel'];
        yield 'no model' => [\stdClass::class];
    }

    public function testAnUnknownRelationTypeThrows(): void
    {
        $this->classes();
        $this->finder->expects(self::never())->method('findOneBy');
        $this->finder->expects(self::never())->method('findBy');

        $this->expectException(UndefinedRelationError::class);
        $this->expectExceptionMessage('The column "tl_test.odd" has the unsupported relation type "manyToMany".');
        $this->resolver()->resolve($this->entry(['id' => 5, 'odd' => 4]), 'odd');
    }

    public function testARelationWithoutTypeThrows(): void
    {
        $this->classes();
        $this->finder->expects(self::never())->method('findOneBy');
        $this->finder->expects(self::never())->method('findBy');

        $this->expectException(UndefinedRelationError::class);
        $this->expectExceptionMessage('The column "tl_test.untyped" has the unsupported relation type "".');
        $this->resolver()->resolve($this->entry(['id' => 5, 'untyped' => 4]), 'untyped');
    }

    public function testTheRelatedClassIsCheckedBeforeTheType(): void
    {
        $this->classes(['tl_nomodel' => new \RuntimeException('no model')]);

        $this->expectException(UndefinedRelationError::class);
        $this->expectExceptionMessage('without a model class');
        $this->resolver()->resolve($this->entry(['id' => 5, 'oddNoModel' => 4]), 'oddNoModel');
    }

    public function testARelationOfATableWithoutModelClassResolves(): void
    {
        $related = new TestModelFixture(['id' => 7]);
        $this->classes(['tl_nomodel' => new \RuntimeException('no model')]);
        $this->finder
            ->expects(self::once())
            ->method('findOneBy')
            ->with(self::identicalTo('id'), self::identicalTo(7))
            ->willReturn($related);

        $entry = $this->entry(['id' => 5, 'pid' => 7], 'tl_nomodel');

        self::assertSame($related, $this->resolver('tl_nomodel')->resolve($entry, 'pid'));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function entry(array $row, string $table = 'tl_test'): Entry
    {
        $entry = EntryCollection::forTable($table, [$row])->first();
        self::assertNotNull($entry);

        return $entry;
    }

    /**
     * Every table maps to {@see TestModelFixture}, except the ones given: a class name, or an exception to throw.
     *
     * @param array<string, string|\Throwable> $lookups
     */
    private function classes(array $lookups = []): void
    {
        $this->models->method('getClassFromTable')->willReturnCallback(
            static function (string $table) use ($lookups): string {
                $lookup = $lookups[$table] ?? TestModelFixture::class;

                if ($lookup instanceof \Throwable) {
                    throw $lookup;
                }

                return $lookup;
            },
        );
    }

    private function expectNoLookup(): void
    {
        $this->models->expects(self::never())->method('getClassFromTable');
        $this->finder->expects(self::never())->method('findOneBy');
        $this->finder->expects(self::never())->method('findBy');
        $this->files->expects(self::never())->method('findMultipleByUuids');
    }

    /**
     * @param string $table the entry's table: its relations are {@see self::RELATIONS}
     */
    private function resolver(string $table = 'tl_test'): EntryRelationResolver
    {
        $extractor = $this->createMock(DcaExtractor::class);
        $extractor->method('getRelations')->willReturn(self::RELATIONS);
        $extractors = $this->mockAdapter(['getInstance']);
        $extractors->method('getInstance')->with(self::identicalTo($table))->willReturn($extractor);

        $framework = $this->createMock(ContaoFramework::class);
        $framework->method('getAdapter')->willReturnCallback(
            fn (string $class): Adapter => match ($class) {
                Model::class => $this->models,
                TestModelFixture::class => $this->finder,
                FilesModel::class => $this->files,
                DcaExtractor::class => $extractors,
            },
        );

        return new EntryRelationResolver($framework, $this->connection);
    }
}
