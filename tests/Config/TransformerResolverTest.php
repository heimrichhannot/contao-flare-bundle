<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Config;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use PHPUnit\Framework\TestCase;

final class TransformerResolverTest extends TestCase
{
    public function testResolvesRegisteredSourceClass(): void
    {
        $transformers = new TransformerResolver();
        $transformer = static function (ConfigBuilder $config, object $source): void {};

        $result = $transformers->for(SourceA::class, $transformer);

        self::assertSame($transformers, $result);
        self::assertSame($transformer, $transformers->resolve(new SourceA()));
    }

    public function testResolvesSubclassSources(): void
    {
        $transformers = new TransformerResolver();
        $transformer = static function (ConfigBuilder $config, object $source): void {};

        $transformers->for(SourceA::class, $transformer);

        self::assertSame($transformer, $transformers->resolve(new SourceASub()));
    }

    public function testReRegistrationOverrides(): void
    {
        $transformers = new TransformerResolver();
        $first = static function (ConfigBuilder $config, object $source): void {};
        $second = static function (ConfigBuilder $config, object $source): void {};

        $transformers->for(SourceA::class, $first);
        $transformers->for(SourceA::class, $second);

        self::assertSame($second, $transformers->resolve(new SourceA()));
    }

    public function testReturnsNullWithoutMatch(): void
    {
        $transformers = new TransformerResolver();
        $transformers->for(SourceA::class, static function (ConfigBuilder $config, object $source): void {});

        self::assertNull($transformers->resolve(new SourceB()));
    }

    public function testExactClassMatchWinsOverEarlierBaseClassRegistration(): void
    {
        $transformers = new TransformerResolver();
        $base = static function (ConfigBuilder $config, object $source): void {};
        $specific = static function (ConfigBuilder $config, object $source): void {};

        $transformers->for(SourceA::class, $base);
        $transformers->for(SourceASub::class, $specific);

        self::assertSame($specific, $transformers->resolve(new SourceASub()));
        self::assertSame($base, $transformers->resolve(new SourceA()));
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
