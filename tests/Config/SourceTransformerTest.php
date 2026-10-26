<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Config;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use PHPUnit\Framework\TestCase;

final class SourceTransformerTest extends TestCase
{
    public function testConfiguresEachKeyOnceAndRunsTheMatchingTransformer(): void
    {
        $calls = 0;
        $configure = static function (TransformerMap $transformers) use (&$calls): void {
            $calls++;
            $transformers->for(\ArrayObject::class, static function (ConfigBuilder $config, \ArrayObject $source): void {
                $config->set('count', \count($source));
            });
        };

        $transformer = new SourceTransformer();

        self::assertSame(['count' => 1], $transformer->transform('a', $configure, new \ArrayObject([1])));
        self::assertSame(['count' => 2], $transformer->transform('a', $configure, new \ArrayObject([1, 2])));
        self::assertSame(1, $calls);

        $transformer->transform('b', $configure, new \ArrayObject());
        self::assertSame(2, $calls);
    }

    public function testReturnsNullWithoutAMatchingTransformer(): void
    {
        self::assertNull((new SourceTransformer())->transform('a', static function (): void {}, new \stdClass()));
    }
}
