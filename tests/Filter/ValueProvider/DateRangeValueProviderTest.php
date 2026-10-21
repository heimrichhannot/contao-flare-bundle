<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\DateRangeValueProvider;
use PHPUnit\Framework\TestCase;

final class DateRangeValueProviderTest extends TestCase
{
    public function testEpochFromAndOpenEnd(): void
    {
        $value = (new DateRangeValueProvider())->provide(\serialize([0, null]), new ProviderStubElement());

        self::assertSame(0, $value->from->getTimestamp());
        self::assertNull($value->to);
    }

    public function testStringTimestampsAreAccepted(): void
    {
        $value = (new DateRangeValueProvider())->provide(\serialize(['100', '200']), new ProviderStubElement());

        self::assertSame([100, 200], [$value->from->getTimestamp(), $value->to->getTimestamp()]);
    }

    public function testEmptyStoredValueIsNoValue(): void
    {
        $p = new DateRangeValueProvider();
        $el = new ProviderStubElement();

        self::assertNull($p->provide('', $el));
        self::assertNull($p->provide(null, $el));
        self::assertNull($p->provide(\serialize([null, '']), $el));
    }

    /**
     * @dataProvider provideUnreadable
     */
    public function testUnreadableValueFailsClosed(mixed $stored): void
    {
        $this->expectException(UnreadableValueException::class);

        (new DateRangeValueProvider())->provide($stored, new ProviderStubElement());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideUnreadable(): iterable
    {
        yield 'non numeric entry' => [\serialize(['x', null])];
        yield 'not serialized' => ['garbage'];
        yield 'too many entries' => [\serialize([1, 2, 3])];
    }
}
