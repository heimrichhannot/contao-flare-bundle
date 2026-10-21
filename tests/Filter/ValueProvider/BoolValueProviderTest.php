<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\BoolValueProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BoolValueProviderTest extends TestCase
{
    public function testMapsStoredFlags(): void
    {
        $p = $this->provider();
        $el = new ProviderStubElement();

        self::assertTrue($p->provide('1', $el)->state);
        self::assertFalse($p->provide('0', $el)->state);
        self::assertNull($p->provide('', $el));
        self::assertNull($p->provide(null, $el));
    }

    public function testUnreadableFlagFailsClosed(): void
    {
        $this->expectException(UnreadableValueException::class);

        $this->provider()->provide('yes', new ProviderStubElement());
    }

    private function provider(): BoolValueProvider
    {
        return new BoolValueProvider($this->createStub(TranslatorInterface::class));
    }
}
