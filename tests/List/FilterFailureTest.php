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

    public function testToListExceptionJoinsTheMessages(): void
    {
        $e = FilterFailure::toListException(
            [new FilterFailure('tl_flare_filter.3', 'first'), new FilterFailure(null, 'second')],
            'tl_flare_list.1',
            'Some::method',
        );

        self::assertSame('[FLARE] The list has failed: first; second', $e->getMessage());
        self::assertSame('tl_flare_list.1', $e->getSource());
        self::assertSame('Some::method', $e->getMethod());
    }

    public function testToListExceptionChainsTheFirstCause(): void
    {
        $first = new FilterException('first cause');
        $e = FilterFailure::toListException(
            [
                new FilterFailure('a', 'duplicate alias'),
                new FilterFailure('b', 'first', $first),
                new FilterFailure('c', 'second', new FilterException('second cause')),
            ],
            'tl_flare_list.1',
            'Some::method',
        );

        self::assertSame($first, $e->getPrevious());
    }

    public function testToListExceptionWithoutCausesHasNoPrevious(): void
    {
        $e = FilterFailure::toListException([new FilterFailure('a', 'duplicate alias')], 'tl_flare_list.1', 'M::m');

        self::assertNull($e->getPrevious());
    }
}
