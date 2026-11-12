<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\ListEntry;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DcaExtractor;
use Contao\Model;
use Contao\Model\Collection;
use Contao\Model\Registry;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryModelFactory;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryRelationResolver;
use HeimrichHannot\FlareBundle\Exception\MissingModelRefError;
use HeimrichHannot\FlareBundle\Exception\UndefinedRelationError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\Tests\Fixture\TestModelFixture;

class EntryRelationResolverTest extends ContaoTestCase
{
    private const RELATIONS = [
        'pid' => ['type' => 'belongsTo', 'table' => 'tl_archive', 'field' => 'id'],
        'tags' => ['type' => 'belongsToMany', 'table' => 'tl_tag', 'field' => 'id'],
        'parent' => ['type' => 'belongsTo', 'table' => 'tl_test', 'field' => 'id'],
    ];

    public function testToOneIsTheModelGetRelatedReturns(): void
    {
        $archive = new TestModelFixture(['id' => 10]);
        $model = $this->createMock(TestModelFixture::class);
        $model->expects(self::once())->method('getRelated')->with('pid')->willReturn($archive);
        $resolver = $this->resolver($model);
        $entry = $this->entry();

        self::assertSame($archive, $resolver->resolve($entry, 'pid'));
        self::assertSame($archive, $resolver->resolve($entry, 'pid'));
    }

    public function testToManyIsTheCollectionGetRelatedReturns(): void
    {
        $tags = new Collection([new TestModelFixture(['id' => 3])], 'tl_test');
        $model = $this->createMock(TestModelFixture::class);
        $model->expects(self::once())->method('getRelated')->with('tags')->willReturn($tags);

        self::assertSame($tags, $this->resolver($model)->resolve($this->entry(), 'tags'));
    }

    public function testARelationIntoTheListsOwnTableIsResolved(): void
    {
        $parent = new TestModelFixture(['id' => 2]);
        $model = $this->createMock(TestModelFixture::class);
        $model->method('getRelated')->with('parent')->willReturn($parent);

        self::assertSame($parent, $this->resolver($model)->resolve($this->entry(), 'parent'));
    }

    public function testAMissingRelatedRecordIsNull(): void
    {
        $model = $this->createMock(TestModelFixture::class);
        $model->method('getRelated')->willReturn(null);

        self::assertNull($this->resolver($model)->resolve($this->entry(), 'pid'));
    }

    public function testEachEntryAndColumnIsResolvedOnItsOwn(): void
    {
        $model = $this->createMock(TestModelFixture::class);
        $model->expects(self::exactly(3))->method('getRelated')->willReturn(null);
        $resolver = $this->resolver($model);
        $first = $this->entry();
        $second = $this->entry();

        $resolver->resolve($first, 'pid');
        $resolver->resolve($first, 'parent');
        $resolver->resolve($second, 'pid');
        $resolver->resolve($second, 'pid');
    }

    public function testWithoutModelRefThrows(): void
    {
        $this->expectException(MissingModelRefError::class);
        $this->resolver($this->createMock(TestModelFixture::class))->resolve(new Entry(['id' => 1]), 'pid');
    }

    public function testAColumnWithoutRelationThrows(): void
    {
        $this->expectException(UndefinedRelationError::class);
        $this->expectExceptionMessage('title');
        $this->resolver($this->createMock(TestModelFixture::class))->resolve($this->entry(), 'title');
    }

    public function testATableWithoutModelClassThrows(): void
    {
        $this->expectException(UndefinedRelationError::class);
        $this->resolver($this->createMock(TestModelFixture::class), classLookupThrows: true)
            ->resolve($this->entry(), 'pid');
    }

    private function entry(): Entry
    {
        $entry = EntryCollection::forTable('tl_test', [['id' => 5]])->first();
        self::assertNotNull($entry);

        return $entry;
    }

    private function resolver(Model $model, bool $classLookupThrows = false): EntryRelationResolver
    {
        $modelAdapter = $this->mockAdapter(['getClassFromTable']);
        if ($classLookupThrows) {
            $modelAdapter->method('getClassFromTable')->willThrowException(new \RuntimeException('no model'));
        } else {
            $modelAdapter->method('getClassFromTable')->willReturn(TestModelFixture::class);
        }

        $registry = $this->createMock(Registry::class);
        $registry->method('fetch')->willReturn($model);
        $registryAdapter = $this->mockAdapter(['getInstance']);
        $registryAdapter->method('getInstance')->willReturn($registry);

        $extractor = $this->createMock(DcaExtractor::class);
        $extractor->method('getRelations')->willReturn(self::RELATIONS);
        $extractorAdapter = $this->mockAdapter(['getInstance']);
        $extractorAdapter->method('getInstance')->willReturn($extractor);

        $framework = $this->createMock(ContaoFramework::class);
        $framework->method('getAdapter')->willReturnCallback(
            static fn (string $class) => match ($class) {
                Model::class => $modelAdapter,
                Registry::class => $registryAdapter,
                DcaExtractor::class => $extractorAdapter,
            },
        );

        return new EntryRelationResolver($framework, new EntryModelFactory($framework));
    }
}
