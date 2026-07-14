<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Config;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use PHPUnit\Framework\TestCase;

final class ConfigBuilderTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        self::assertSame([], (new ConfigBuilder())->all());
    }

    public function testSetIsFluentAndAccumulates(): void
    {
        $config = new ConfigBuilder();

        $result = $config
            ->set('intrinsic', true)
            ->set('left', 'id')
            ->set('right', null);

        self::assertSame($config, $result);
        self::assertSame(['intrinsic' => true, 'left' => 'id', 'right' => null], $config->all());
    }

    public function testSetOverwritesSameKey(): void
    {
        $config = new ConfigBuilder();

        $config->set('field', 'a')->set('field', 'b');

        self::assertSame(['field' => 'b'], $config->all());
    }
}
