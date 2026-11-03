<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Filter\ValueProvider\KeywordsValueProvider;
use PHPUnit\Framework\TestCase;

final class KeywordsValueProviderTest extends TestCase
{
    public function testProvidesNormalizedKeywords(): void
    {
        $value = (new KeywordsValueProvider())->provide("  foo \n bar ", new ProviderStubElement());

        self::assertSame('foo bar', $value->keywords);
    }

    public function testBlankIsNoValue(): void
    {
        $p = new KeywordsValueProvider();

        self::assertNull($p->provide('  ', new ProviderStubElement()));
        self::assertNull($p->provide(null, new ProviderStubElement()));
    }
}
