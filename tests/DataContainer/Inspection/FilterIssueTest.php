<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection;

use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssue;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueKind;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

final class FilterIssueTest extends TestCase
{
    public function testIneffectiveIsDescribedByItsReason(): void
    {
        self::assertSame('No value.', self::describe(FilterIssueKind::Ineffective, 'No value.', true));
    }

    public function testDuplicateIsDescribedWithItsAlias(): void
    {
        self::assertSame(
            'The alias "dup" is also used by another published filter, which blocks the list.',
            self::describe(FilterIssueKind::DuplicateAlias, 'dup', true),
        );
    }

    public function testUnpublishedDuplicateWording(): void
    {
        self::assertSame(
            'Once published, this filter\'s alias "dup" would clash with another filter and block the list.',
            self::describe(FilterIssueKind::DuplicateAlias, 'dup', false),
        );
    }

    public function testProgrammingErrorWording(): void
    {
        self::assertSame(
            'A programming error occurred in this unpublished filter; an administrator may need to be contacted: bug',
            self::describe(FilterIssueKind::ProgrammingError, 'bug', false),
        );
    }

    public function testBrokenWording(): void
    {
        self::assertSame('This filter breaks the list: x', self::describe(FilterIssueKind::Broken, 'x', true));
    }

    private static function describe(FilterIssueKind $kind, string $reason, bool $published): string
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__ . '/../../../translations/flare.en.yaml', 'en', 'flare');

        return (new FilterIssue($kind, $reason, $published))->describe($translator);
    }
}
