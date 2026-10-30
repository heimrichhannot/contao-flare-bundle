<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilder;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\DateRangeValueProvider;
use HeimrichHannot\FlareBundle\Model\ListModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

final class DateRangeValueProviderTest extends TestCase
{
    public function testEpochFromAndOpenEnd(): void
    {
        $value = self::provider()->provide(\serialize([0, null]), new ProviderStubElement());

        self::assertSame(0, $value->from->getTimestamp());
        self::assertNull($value->to);
    }

    public function testStringTimestampsAreAccepted(): void
    {
        $value = self::provider()->provide(\serialize(['100', '200']), new ProviderStubElement());

        self::assertSame([100, 200], [$value->from->getTimestamp(), $value->to->getTimestamp()]);
    }

    public function testExpressionsAreResolvedWhenTheValueIsProvided(): void
    {
        $value = self::provider()->provide(\serialize(['today', 'tomorrow']), new ProviderStubElement());

        self::assertSame(\strtotime('today'), $value->from->getTimestamp());
        self::assertSame(\strtotime('tomorrow'), $value->to->getTimestamp());
    }

    public function testDateStringsAndTimeSpanKeysAreAccepted(): void
    {
        $value = self::provider()->provide(\serialize(['2026-10-26', 'this_month']), new ProviderStubElement());

        self::assertSame(\strtotime('2026-10-26'), $value->from->getTimestamp());
        self::assertSame(\strtotime('first day of this month'), $value->to->getTimestamp());
    }

    public function testEmptyStoredValueIsNoValue(): void
    {
        $p = self::provider();
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

        self::provider()->provide($stored, new ProviderStubElement());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideUnreadable(): iterable
    {
        yield 'no time expression' => [\serialize(['not a date', null])];
        yield 'not serialized' => ['garbage'];
        yield 'too many entries' => [\serialize([1, 2, 3])];
    }

    public function testTheBackendFieldHasNoRgxp(): void
    {
        self::assertArrayNotHasKey('rgxp', self::field()['eval']);
    }

    public function testSavingStoresTheTrimmedExpressions(): void
    {
        self::assertSame(\serialize(['today', '+1 week']), self::save(\serialize([' today ', '+1 week'])));
        self::assertSame(\serialize(['2026-10-26', null]), self::save(\serialize(['2026-10-26', ''])));
    }

    public function testSavingTwoEmptyEntriesStoresNoValue(): void
    {
        self::assertNull(self::save(\serialize(['', ''])));
    }

    public function testSavingAnInvalidExpressionIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);

        self::save(\serialize(['today', 'not a date']));
    }

    private static function provider(): DateRangeValueProvider
    {
        return new DateRangeValueProvider(new IdentityTranslator());
    }

    /**
     * @return array<string, mixed>
     */
    private static function field(): array
    {
        $builder = new DcaFieldBuilder();
        self::provider()->buildDcaField($builder, new DcaContext(
            'tl_flare_filter',
            'test',
            (new \ReflectionClass(ListModel::class))->newInstanceWithoutConstructor(),
            null,
            static fn (): null => null,
        ));

        $definition = [];
        $builder->applyTo($definition);

        return $definition;
    }

    private static function save(mixed $value): mixed
    {
        foreach (self::field()['save_callback'] as $callback) {
            $value = $callback($value);
        }

        return $value;
    }
}
