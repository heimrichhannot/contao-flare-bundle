<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Config;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use PHPUnit\Framework\TestCase;

final class TransformerMapTest extends TestCase
{
    public function testFindsTheTransformerOfTheSourceClass(): void
    {
        $transformers = new TransformerMap();
        $transformer = static function (ConfigBuilder $config, object $source): void {};

        $result = $transformers->set(SourceA::class, $transformer);

        self::assertSame($transformers, $result);
        self::assertSame($transformer, $transformers->find(new SourceA()));
    }

    public function testFindsTheTransformerForSubclassSources(): void
    {
        $transformers = new TransformerMap();
        $transformer = static function (ConfigBuilder $config, object $source): void {};

        $transformers->set(SourceA::class, $transformer);

        self::assertSame($transformer, $transformers->find(new SourceASub()));
    }

    public function testSettingAgainReplaces(): void
    {
        $transformers = new TransformerMap();
        $first = static function (ConfigBuilder $config, object $source): void {};
        $second = static function (ConfigBuilder $config, object $source): void {};

        $transformers->set(SourceA::class, $first);
        $transformers->set(SourceA::class, $second);

        self::assertSame($second, $transformers->find(new SourceA()));
    }

    public function testReturnsNullWithoutMatch(): void
    {
        $transformers = new TransformerMap();
        $transformers->set(SourceA::class, static function (ConfigBuilder $config, object $source): void {});

        self::assertNull($transformers->find(new SourceB()));
    }

    public function testExactClassMatchWinsOverEarlierBaseClassRegistration(): void
    {
        $transformers = new TransformerMap();
        $base = static function (ConfigBuilder $config, object $source): void {};
        $specific = static function (ConfigBuilder $config, object $source): void {};

        $transformers->set(SourceA::class, $base);
        $transformers->set(SourceASub::class, $specific);

        self::assertSame($specific, $transformers->find(new SourceASub()));
        self::assertSame($base, $transformers->find(new SourceA()));
    }
}

class SourceA
{
}

final class SourceASub extends SourceA
{
}

final class SourceB
{
}
