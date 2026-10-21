<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceKey;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ChoiceValueProvider;
use PHPUnit\Framework\TestCase;

final class ChoiceValueProviderTest extends TestCase
{
    public function testResolvesStoredKeysToReferenceChoices(): void
    {
        $value = (new ChoiceValueProvider())->provide(\serialize(['k1']), new ProviderStubChoiceElement());

        self::assertSame(['k1'], \array_map(ChoiceKey::of(...), $value->choices));
    }

    public function testUnreadableKeysAreSkippedWhenOthersResolve(): void
    {
        $value = (new ChoiceValueProvider())
            ->provide(\serialize(['k2', 'garbage', 'k1']), new ProviderStubChoiceElement());

        self::assertSame(['k1', 'k2'], \array_map(ChoiceKey::of(...), $value->choices));
    }

    public function testEmptyStoredValueIsNoValue(): void
    {
        $p = new ChoiceValueProvider();
        $el = new ProviderStubChoiceElement();

        self::assertNull($p->provide('', $el));
        self::assertNull($p->provide(null, $el));
        self::assertNull($p->provide(\serialize([]), $el));
    }

    public function testUnreadableNonEmptyValueFailsClosed(): void
    {
        $this->expectException(UnreadableValueException::class);

        (new ChoiceValueProvider())->provide(\serialize(['garbage']), new ProviderStubChoiceElement());
    }

    public function testNonSerializedValueFailsClosed(): void
    {
        $this->expectException(UnreadableValueException::class);

        (new ChoiceValueProvider())->provide('k1', new ProviderStubChoiceElement());
    }

    public function testElementWithoutChoiceSourceIsRejected(): void
    {
        $this->expectException(FilterException::class);

        (new ChoiceValueProvider())->provide(\serialize(['k1']), new ProviderStubElement());
    }
}
