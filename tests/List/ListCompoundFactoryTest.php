<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\Factory\ListCompoundFactory;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListCompoundFactoryTest extends TestCase
{
    private function createFactory(?ListDriverRegistry $registry = null): ListCompoundFactory
    {
        $registry ??= new ListDriverRegistry();

        return new ListCompoundFactory(
            $registry,
            new ListOptionsResolver(new SchemaResolver()),
            new ListTransformerResolver(new SourceTransformer(), new EventDispatcher()),
            new ListDriverResolver($registry),
        );
    }

    public function testCreatesListWithResolvedConfigAndDc(): void
    {
        $driver = new class extends AbstractListDriver {};

        $list = $this->createFactory()->create(
            driver: $driver,
            config: ['dc' => 'tl_test', 'title' => 'My List'],
            source: 'tl_flare_list.1',
        );

        self::assertSame($driver, $list->driver);
        self::assertSame('tl_test', $list->dc);
        self::assertSame('tl_test', $list->config['dc']);
        self::assertSame('My List', $list->config['title']);
        self::assertFalse($list->config['genericPageMeta']); // schema default applied
        self::assertSame('tl_flare_list.1', $list->source);
    }

    public function testResolvesDriverFromRegisteredTypeAlias(): void
    {
        $driver = new class extends AbstractListDriver {};

        $registry = new ListDriverRegistry();
        $registry->add($driver, null, 'my_type');

        $list = $this->createFactory($registry)->create(driver: 'my_type', config: ['dc' => 'tl_test']);

        self::assertSame($driver, $list->driver);
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
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_news';
            }
        };

        $list = $this->createFactory()->create(driver: $driver);

        self::assertSame('tl_news', $list->dc);
        self::assertSame('', $list->config['dc']); // dc lives on the spec; config keeps its own value
    }
}
