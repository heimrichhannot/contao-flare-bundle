<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Inspection;

use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Contao\Inspection\FilterIssue;
use HeimrichHannot\FlareBundle\Contao\Inspection\FilterIssueKind;
use HeimrichHannot\FlareBundle\Contao\Inspection\InspectedRows;
use HeimrichHannot\FlareBundle\Model\FilterModel;

final class InspectedRowsTest extends ContaoTestCase
{
    /**
     * @dataProvider provideSources
     */
    public function testIdOfNamesOnlyARowOfThisList(?string $source, ?int $expected): void
    {
        $rows = new InspectedRows();
        $rows->add($this->mockClassWithProperties(FilterModel::class, ['id' => 42]));

        self::assertSame($expected, $rows->idOf($source));
    }

    /**
     * @return iterable<string, array{?string, ?int}>
     */
    public static function provideSources(): iterable
    {
        yield 'a row of this list' => ['tl_flare_filter.42', 42];
        yield 'a row of another list' => ['tl_flare_filter.43', null];
        yield 'another table' => ['tl_content.42', null];
        yield 'no source' => [null, null];
        yield 'no id' => ['tl_flare_filter.', null];
        yield 'zero' => ['tl_flare_filter.0', null];
        yield 'negative' => ['tl_flare_filter.-42', null];
        yield 'decimal' => ['tl_flare_filter.42.0', null];
        yield 'not a number' => ['tl_flare_filter.x', null];
    }

    public function testTheModelsAreKeptById(): void
    {
        $rows = new InspectedRows();
        $model = $this->row(42);
        $rows->add($model);

        self::assertSame([42 => $model], $rows->getModels());
    }

    public function testARowKeepsSeveralIssuesOfOneKind(): void
    {
        $rows = new InspectedRows();
        $rows->add($this->row(1));
        $rows->addIssue(1, FilterIssueKind::Warning, 'first');
        $rows->addIssue(1, FilterIssueKind::Warning, 'second', 'opacity');

        $expected = [
            new FilterIssue(FilterIssueKind::Warning, 'first', true),
            new FilterIssue(FilterIssueKind::Warning, 'second', true, 'opacity'),
        ];

        self::assertEquals($expected, $rows->issuesOf(1));
        self::assertEquals($expected, $rows->toInspection()->forRow(1));
        self::assertSame(1, $rows->toInspection()->count(FilterIssueKind::Warning));
    }

    public function testAnEqualIssueIsKeptOnce(): void
    {
        $rows = new InspectedRows();
        $rows->add($this->row(1));
        $rows->addIssue(1, FilterIssueKind::Broken, 'broken');
        $rows->addIssue(1, FilterIssueKind::Broken, 'broken');

        self::assertEquals([new FilterIssue(FilterIssueKind::Broken, 'broken', true)], $rows->issuesOf(1));
        self::assertCount(1, $rows->toInspection()->forRow(1));
    }

    public function testEqualTextWithAnotherIconIsKept(): void
    {
        $rows = new InspectedRows();
        $rows->add($this->row(1));
        $rows->addIssue(1, FilterIssueKind::Warning, 'hint', 'opacity');
        $rows->addIssue(1, FilterIssueKind::Warning, 'hint');

        self::assertEquals([
            new FilterIssue(FilterIssueKind::Warning, 'hint', true, 'opacity'),
            new FilterIssue(FilterIssueKind::Warning, 'hint', true),
        ], $rows->toInspection()->forRow(1));
    }

    public function testIssuesAreOrderedByKind(): void
    {
        $rows = new InspectedRows();
        $rows->add($this->row(1));
        $rows->addIssue(1, FilterIssueKind::Warning, 'hint');
        $rows->addIssue(1, FilterIssueKind::Broken, 'broken');

        $kinds = \array_map(
            static fn (FilterIssue $issue): FilterIssueKind => $issue->kind,
            $rows->toInspection()->forRow(1),
        );

        self::assertSame([FilterIssueKind::Broken, FilterIssueKind::Warning], $kinds);
    }

    private function row(int $id): FilterModel
    {
        return $this->mockClassWithProperties(FilterModel::class, ['id' => $id, 'published' => '1']);
    }
}
