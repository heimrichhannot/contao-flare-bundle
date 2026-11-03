<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\BelongsToRelationFilterElement;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BelongsToRelationFilterElementTest extends TestCase
{
    use BuildsFilterContextsTrait;
    use StubsContaoDcaTrait;

    public function testAParentTableThatCannotBeInferredFailsTheFilter(): void
    {
        // A data container FLARE cannot read is a configuration it cannot honour, not data matching nothing.
        $this->stubDca('tl_missing', []);

        $this->expectException(FilterException::class);

        $this->buildFilterContext(
            new BelongsToRelationFilterElement($this->createMock(TranslatorInterface::class)),
            ['field_pid' => 'pid'],
            dc: 'tl_missing',
        );
    }
}
