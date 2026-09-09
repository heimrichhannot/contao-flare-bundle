<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Config;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SchemaResolverTest extends TestCase
{
    public function testConfiguratorRunsOncePerKey(): void
    {
        $schemaResolver = new SchemaResolver();

        $calls = 0;
        $configure = function (OptionsResolver $resolver) use (&$calls): void {
            $calls++;
            $resolver->define('foo')->default('bar')->allowedTypes('string');
        };

        self::assertSame(['foo' => 'bar'], $schemaResolver->resolve('key_a', $configure, []));
        self::assertSame(['foo' => 'baz'], $schemaResolver->resolve('key_a', $configure, ['foo' => 'baz']));
        self::assertSame(1, $calls);

        self::assertSame(['foo' => 'bar'], $schemaResolver->resolve('key_b', $configure, []));
        self::assertSame(2, $calls);
    }

    public function testResolutionFailuresPropagateUntouched(): void
    {
        $schemaResolver = new SchemaResolver();

        $configure = static function (OptionsResolver $resolver): void {
            $resolver->define('foo')->default(null)->allowedTypes('string', 'null');
        };

        $this->expectException(UndefinedOptionsException::class);

        $schemaResolver->resolve('key', $configure, ['unknown' => 1]);
    }

    public function testFailedConfiguratorIsNotMemoized(): void
    {
        $schemaResolver = new SchemaResolver();

        $calls = 0;
        $configure = function (OptionsResolver $resolver) use (&$calls): void {
            if (1 === ++$calls) {
                throw new \RuntimeException('seeding failed');
            }

            $resolver->define('foo')->default('bar')->allowedTypes('string');
        };

        try
        {
            $schemaResolver->resolve('key', $configure, []);
            self::fail('Expected RuntimeException.');
        }
        catch (\RuntimeException $e)
        {
            self::assertSame('seeding failed', $e->getMessage());
        }

        self::assertSame(['foo' => 'bar'], $schemaResolver->resolve('key', $configure, []));
        self::assertSame(2, $calls);
    }
}
