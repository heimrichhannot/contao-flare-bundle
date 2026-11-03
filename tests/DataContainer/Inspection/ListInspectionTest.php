<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection;

use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssue;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueKind;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\ListInspection;
use PHPUnit\Framework\TestCase;

final class ListInspectionTest extends TestCase
{
    public function testRowsWithoutIssuesHaveNone(): void
    {
        $issue = new FilterIssue(FilterIssueKind::Broken, 'x', true);
        $inspection = new ListInspection(rows: [4 => [$issue]]);

        self::assertSame([], $inspection->forRow(5));
        self::assertSame([$issue], $inspection->forRow(4));
    }

    public function testKindsWithoutEntryCountZero(): void
    {
        $inspection = new ListInspection(counts: [FilterIssueKind::Broken->value => 2]);

        self::assertSame(0, $inspection->count(FilterIssueKind::Ineffective));
        self::assertSame(2, $inspection->count(FilterIssueKind::Broken));
    }
}
