<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ChoiceValueProvider;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceKey;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Translation\IdentityTranslator;

final class ChoiceValueProviderTest extends TestCase
{
    public function testResolvesStoredKeysToReferenceChoices(): void
    {
        $value = (self::provider())->provide(\serialize(['k1']), new ProviderStubChoiceElement());

        self::assertSame(['k1'], \array_map(ChoiceKey::of(...), $value->choices));
    }

    public function testUnreadableKeysAreSkippedWhenOthersResolve(): void
    {
        $value = (self::provider())
            ->provide(\serialize(['k2', 'garbage', 'k1']), new ProviderStubChoiceElement());

        self::assertSame(['k1', 'k2'], \array_map(ChoiceKey::of(...), $value->choices));
    }

    public function testEmptyStoredValueIsNoValue(): void
    {
        $p = self::provider();
        $el = new ProviderStubChoiceElement();

        self::assertNull($p->provide('', $el));
        self::assertNull($p->provide(null, $el));
        self::assertNull($p->provide(\serialize([]), $el));
    }

    public function testUnreadableNonEmptyValueFailsClosed(): void
    {
        $this->expectException(UnreadableValueException::class);

        (self::provider())->provide(\serialize(['garbage']), new ProviderStubChoiceElement());
    }

    public function testNonSerializedValueFailsClosed(): void
    {
        $this->expectException(UnreadableValueException::class);

        (self::provider())->provide('k1', new ProviderStubChoiceElement());
    }

    public function testElementWithoutChoiceSourceIsRejected(): void
    {
        $this->expectException(FilterException::class);

        (self::provider())->provide(\serialize(['k1']), new ProviderStubElement());
    }

    private static function provider(): ChoiceValueProvider
    {
        return new ChoiceValueProvider(
            new ChoicesBuilderFactory(new IdentityTranslator(), new ParameterBag()),
        );
    }
}
