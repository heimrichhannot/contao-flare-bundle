<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use PHPUnit\Framework\TestCase;

final class FilterModelTest extends TestCase
{
    public function testFilterAliasIsTheAliasColumnOrTheId(): void
    {
        self::assertSame('my-filter', self::model(FilterModel::class, ['id' => 3, 'alias' => 'my-filter'])
            ->getFilterAlias());
        self::assertSame('3', self::model(FilterModel::class, ['id' => 3, 'alias' => ''])->getFilterAlias());

        self::assertSame('ab', FilterModel::generateFormName(['id' => 3, 'alias' => ' a b! ']));
        self::assertSame('3', FilterModel::generateFormName(['id' => 3, 'formAlias' => 'legacy']));
    }

    public function testDuplicateFormNamesAreFoundAfterNormalization(): void
    {
        self::assertSame(['newsarchive', '7'], FilterModel::findDuplicateFormNames([
            ['id' => 1, 'alias' => 'news archive'],
            ['id' => 2, 'alias' => 'newsarchive'],
            ['id' => 3, 'alias' => '7'],
            ['id' => 7, 'alias' => ''],
            ['id' => 8, 'alias' => 'unique'],
            ['id' => 9, 'alias' => 'newsarchive!'],
        ]));

        self::assertSame([], FilterModel::findDuplicateFormNames([['id' => 1, 'alias' => 'a'], ['id' => 2]]));
    }

    public function testFilterPtableInferenceReadsTheElementColumns(): void
    {
        $filter = self::model(FilterModel::class, [
            'elm_fieldPid' => 'parent',
            'elm_whichPtable' => PtableInferrer::WHICH_PTABLE_STATIC,
            'elm_fieldPtable' => 'parentTable',
            'elm_tablePtable' => 'tl_news_archive',
            'fieldPid' => 'stale',
            'whichPtable' => PtableInferrer::WHICH_PTABLE_DYNAMIC,
            'fieldPtable' => 'stale',
            'tablePtable' => 'stale',
        ]);

        self::assertSame('parent', $filter->getInferFieldPid());
        self::assertSame(PtableInferrer::WHICH_PTABLE_STATIC, $filter->getInferWhichPtable());
        self::assertSame('parentTable', $filter->getInferFieldPtable());
        self::assertSame('tl_news_archive', $filter->getInferTablePtable());
    }

    public function testListPtableInferenceReadsTheUnprefixedColumns(): void
    {
        $list = self::model(ListModel::class, [
            'fieldPid' => 'parent',
            'whichPtable' => PtableInferrer::WHICH_PTABLE_DYNAMIC,
            'fieldPtable' => 'parentTable',
            'tablePtable' => '',
        ]);

        self::assertSame('parent', $list->getInferFieldPid());
        self::assertSame(PtableInferrer::WHICH_PTABLE_DYNAMIC, $list->getInferWhichPtable());
        self::assertSame('parentTable', $list->getInferFieldPtable());
        self::assertNull($list->getInferTablePtable());
    }

    /**
     * @template T of Model
     * @param class-string<T>      $class
     * @param array<string, mixed> $row
     * @return T
     */
    private static function model(string $class, array $row): Model
    {
        $model = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

        (function (array $row): void {
            $this->arrData = $row;
        })->call($model, $row);

        return $model;
    }
}
