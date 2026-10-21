<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\ValueClassReflector;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use PHPUnit\Framework\TestCase;

final class ValueClassReflectorTest extends TestCase
{
    public function testReadsTheNullableNarrowedReturnType(): void
    {
        self::assertSame(BoolValue::class, ValueClassReflector::fromReturnType(ReflectorFixture::class, 'nullable'));
    }

    public function testReadsTheNonNullableNarrowedReturnType(): void
    {
        self::assertSame(ChoiceValue::class, ValueClassReflector::fromReturnType(ReflectorFixture::class, 'plain'));
    }

    /**
     * @dataProvider provideRejectedMethods
     */
    public function testRejects(string $method, string $messagePattern): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches($messagePattern);

        ValueClassReflector::fromReturnType(ReflectorFixture::class, $method);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRejectedMethods(): iterable
    {
        yield 'bare interface' => ['bare', '/ValueInterface/'];
        yield 'union' => ['union', '/single/'];
        yield 'mixed' => ['mixedType', '/single/'];
        yield 'no return type' => ['untyped', '/single/'];
        yield 'builtin' => ['builtin', '/single/'];
        yield 'not a value' => ['notAValue', '/implement/'];
        yield 'self' => ['selfType', '/implement/'];
        yield 'missing method' => ['missing', '/does not exist/'];
    }

    public function testRejectsAnUnknownClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ValueClassReflector::fromReturnType('Acme\\NoSuchClass', 'decode');
    }
}

abstract class ReflectorFixture
{
    public function nullable(): ?BoolValue
    {
        return null;
    }

    public function plain(): ChoiceValue
    {
        return new ChoiceValue([]);
    }

    public function bare(): ?ValueInterface
    {
        return null;
    }

    public function union(): BoolValue|ChoiceValue|null
    {
        return null;
    }

    public function mixedType(): mixed
    {
        return null;
    }

    public function untyped()
    {
        return null;
    }

    public function builtin(): ?string
    {
        return null;
    }

    public function notAValue(): ?\stdClass
    {
        return null;
    }

    public function selfType(): ?self
    {
        return null;
    }
}
