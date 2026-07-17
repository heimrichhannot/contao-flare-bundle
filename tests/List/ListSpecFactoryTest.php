<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecFactory;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use PHPUnit\Framework\TestCase;

final class ListSpecFactoryTest extends TestCase
{
    private function createFactory(?ListDriverRegistry $registry = null): ListSpecFactory
    {
        return new ListSpecFactory(
            $registry ?? new ListDriverRegistry(),
            new ListOptionsResolver(new SchemaResolver()),
        );
    }

    public function testCreatesSpecWithResolvedConfigAndDc(): void
    {
        $driver = new class extends AbstractListDriver {};

        $spec = $this->createFactory()->create(
            driver: $driver,
            config: ['dc' => 'tl_test', 'title' => 'My List'],
            source: 'tl_flare_list.1',
        );

        self::assertSame($driver, $spec->driver);
        self::assertSame('tl_test', $spec->getDataContainerName());
        self::assertSame('tl_test', $spec->config['dc']);
        self::assertSame('My List', $spec->config['title']);
        self::assertFalse($spec->config['genericPageMeta']); // schema default applied
        self::assertSame('tl_flare_list.1', $spec->source);
    }

    public function testResolvesDriverFromRegisteredTypeAlias(): void
    {
        $driver = new class extends AbstractListDriver {};

        $registry = new ListDriverRegistry();
        $registry->add($driver, null, 'my_type');

        $spec = $this->createFactory($registry)->create(driver: 'my_type', config: ['dc' => 'tl_test']);

        self::assertSame($driver, $spec->driver);
    }

    public function testThrowsForUnknownTypeAlias(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('List type "missing" not found');

        $this->createFactory()->create(driver: 'missing');
    }

    public function testThrowsWhenNoDataContainerCanBeDetermined(): void
    {
        $this->expectException(FlareException::class);
        $this->expectExceptionMessage('data container');

        $this->createFactory()->create(driver: new class extends AbstractListDriver {});
    }

    public function testDriverPinnedToATableDefinesTheDcRegardlessOfConfig(): void
    {
        $driver = new class extends AbstractListDriver {
            public function getDataContainerName(array $config): string
            {
                return 'tl_news';
            }
        };

        $spec = $this->createFactory()->create(driver: $driver);

        self::assertSame('tl_news', $spec->getDataContainerName());
        self::assertSame('tl_news', $spec->config['dc']);
    }
}
