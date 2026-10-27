<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use PHPUnit\Framework\TestCase;

final class FilterFailureTest extends TestCase
{
    public function testReasonIsTheExceptionsMessage(): void
    {
        $failure = new FilterFailure('tl_flare_filter.3', 'Filter … could not be created: x', new FlareException('x'));

        self::assertSame('x', $failure->getReason());
    }

    public function testReasonUnwrapsTheQueryDeniedWrapper(): void
    {
        $cause = new FilterException('No parent archives are whitelisted.');
        $wrapper = new FilterException(
            '[FLARE] Query denied: No parent archives are whitelisted. / Callback: X::buildContext',
            previous: $cause,
        );

        self::assertSame('No parent archives are whitelisted.', (new FilterFailure('s', 'm', $wrapper))->getReason());
    }

    public function testReasonFallsBackToTheMessageWithoutException(): void
    {
        self::assertSame('The alias "dup" is taken.', (new FilterFailure('s', 'The alias "dup" is taken.'))->getReason());
    }
}
