<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use PHPUnit\Framework\TestCase;

final class ListDriverRegistryTest extends TestCase
{
    public function testAddAndTypedAccessors(): void
    {
        $registry = new ListDriverRegistry();
        $driver = new RegistryDriverStub();
        $attribute = new AsListDriver(type: 'news', dataContainer: 'tl_news');

        $registry->add($driver, $attribute, 'news');

        self::assertTrue($registry->has('news'));
        self::assertSame($driver, $registry->getService('news'));
        self::assertSame($attribute, $registry->getAttribute('news'));
        self::assertSame('tl_news', $registry->getAttribute('news')?->dataContainer);
        self::assertFalse($registry->isInline('news'));
        self::assertSame(['news'], $registry->keys());

        self::assertNull($registry->getService('unknown'));
        self::assertNull($registry->getService(null));
        self::assertNull($registry->getAttribute(null));
    }

    public function testGetTypesMatchesRegisteredInstanceOnly(): void
    {
        $registry = new ListDriverRegistry();
        $registered = new RegistryDriverStub();
        $inline = new RegistryDriverStub();

        $registry->add($registered, null, 'a');
        $registry->add($registered, null, 'b');

        self::assertSame(['a', 'b'], $registry->getTypes($registered));
        self::assertSame([], $registry->getTypes($inline), 'An unregistered instance of a registered class has no types.');
        self::assertSame(['a', 'b'], $registry->getTypes(RegistryDriverStub::class));
    }

    public function testInlineRegistrationUsesClassNameAsType(): void
    {
        $registry = new ListDriverRegistry();
        $driver = new RegistryDriverStub();

        $registry->add($driver);

        self::assertTrue($registry->has(RegistryDriverStub::class));
        self::assertTrue($registry->isInline(RegistryDriverStub::class));
        self::assertSame($driver, $registry->getService(RegistryDriverStub::class));
        self::assertSame([RegistryDriverStub::class], $registry->getTypes($driver));
    }

    public function testOverridingATypePrunesTheReverseMap(): void
    {
        $registry = new ListDriverRegistry();
        $first = new RegistryDriverStub();
        $second = new OtherRegistryDriverStub();

        $registry->add($first, null, 'shared');
        $registry->add($second, null, 'shared');

        self::assertSame($second, $registry->getService('shared'));
        self::assertSame([], $registry->getTypes($first));
        self::assertSame([], $registry->getTypes(RegistryDriverStub::class));
        self::assertSame(['shared'], $registry->getTypes($second));
    }

    public function testRemoveCleansForwardAndReverseMaps(): void
    {
        $registry = new ListDriverRegistry();
        $driver = new RegistryDriverStub();

        $registry->add($driver, null, 'a');
        $registry->add($driver, null, 'b');
        $registry->remove('a');

        self::assertFalse($registry->has('a'));
        self::assertTrue($registry->has('b'));
        self::assertSame(['b'], $registry->getTypes($driver));

        $registry->remove('b');

        self::assertSame([], $registry->getTypes($driver));
        self::assertSame([], $registry->keys());
    }
}

class RegistryDriverStub implements ListDriverInterface
{
    public function getDataContainerName(array $config): string
    {
        return (string) ($config['dc'] ?? '');
    }
}

final class OtherRegistryDriverStub extends RegistryDriverStub
{
}
