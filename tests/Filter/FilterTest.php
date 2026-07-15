<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Filter\Filter;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    public function testWithersPreserveOtherFields(): void
    {
        $filter = new Filter(type: 'test', config: ['a' => 1], alias: 'foo', source: 'tl_flare_filter.1');

        $withData = $filter->withData(['value' => 42]);

        self::assertNull($filter->data);
        self::assertSame(['value' => 42], $withData->data);
        self::assertSame('foo', $withData->alias);
        self::assertSame(['a' => 1], $withData->config);
        self::assertSame('tl_flare_filter.1', $withData->source);

        $targeted = $filter->withTargetAlias('translation');

        self::assertSame('translation', $targeted->targetAlias);
        self::assertTrue($targeted->targetingForced);
        self::assertFalse($filter->targetingForced);
    }

    public function testFingerprintReflectsIdentityAndContent(): void
    {
        $filter = new Filter(type: 'test', config: ['a' => 1], alias: 'foo');

        $fingerprint = $filter->fingerprint();

        self::assertSame('test', $fingerprint['type']);
        self::assertSame(['a' => 1], $fingerprint['config']);
        self::assertSame('foo', $fingerprint['alias']);
        self::assertNotSame($fingerprint, $filter->withConfig(['a' => 2])->fingerprint());
    }
}
