<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Config;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
use PHPUnit\Framework\TestCase;

final class ComponentConfigTest extends TestCase
{
    public function testGetReturnsResolvedValue(): void
    {
        $config = new ComponentConfig(['a' => 1, 'b' => null]);

        self::assertSame(1, $config->get('a'));
        self::assertNull($config->get('b'));
    }

    public function testDefinedButUnsetKeyReadsNull(): void
    {
        $config = new ComponentConfig(['a' => 1], ['a', 'x']);

        self::assertNull($config->get('x'));
        self::assertFalse($config->has('x'));
        self::assertTrue($config->has('a'));
    }

    public function testUndefinedKeyThrowsError(): void
    {
        $config = new ComponentConfig(['columns' => []], null, 'App\FooElement');

        try
        {
            $config->get('columnz');
            self::fail('Expected an UndefinedConfigKeyError.');
        }
        catch (UndefinedConfigKeyError $e)
        {
            self::assertSame(
                'The config key "columnz" is not defined by App\FooElement. Defined keys: columns.',
                $e->getMessage(),
            );
            self::assertSame('columnz', $e->key);
        }
    }

    public function testErrorMessageWithoutOwnerOrDefinedKeys(): void
    {
        self::assertSame(
            'The config key "k" is not defined by the component. Defined keys: none.',
            (new UndefinedConfigKeyError('k', []))->getMessage(),
        );
    }

    public function testErrorIsNotAnException(): void
    {
        self::assertNotInstanceOf(\Exception::class, new UndefinedConfigKeyError('k', []));
    }

    public function testAllReturnsValuesOnly(): void
    {
        self::assertSame(['a' => 1], (new ComponentConfig(['a' => 1], ['a', 'x']))->all());
    }
}
