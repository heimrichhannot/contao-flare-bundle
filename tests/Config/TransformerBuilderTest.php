<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Config;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerBuilder;
use PHPUnit\Framework\TestCase;

final class TransformerBuilderTest extends TestCase
{
    public function testResolvesRegisteredSourceClass(): void
    {
        $transformers = new TransformerBuilder();
        $transformer = static function (object $source, ConfigBuilder $config): void {};

        $result = $transformers->for(SourceA::class, $transformer);

        self::assertSame($transformers, $result);
        self::assertSame($transformer, $transformers->resolve(new SourceA()));
    }

    public function testResolvesSubclassSources(): void
    {
        $transformers = new TransformerBuilder();
        $transformer = static function (object $source, ConfigBuilder $config): void {};

        $transformers->for(SourceA::class, $transformer);

        self::assertSame($transformer, $transformers->resolve(new SourceASub()));
    }

    public function testReRegistrationOverrides(): void
    {
        $transformers = new TransformerBuilder();
        $first = static function (object $source, ConfigBuilder $config): void {};
        $second = static function (object $source, ConfigBuilder $config): void {};

        $transformers->for(SourceA::class, $first);
        $transformers->for(SourceA::class, $second);

        self::assertSame($second, $transformers->resolve(new SourceA()));
    }

    public function testReturnsNullWithoutMatch(): void
    {
        $transformers = new TransformerBuilder();
        $transformers->for(SourceA::class, static function (object $source, ConfigBuilder $config): void {});

        self::assertNull($transformers->resolve(new SourceB()));
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
