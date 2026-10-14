<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\ListTransformerEvent;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListTransformerResolverTest extends TestCase
{
    public function testTransformsSourceThroughDriverTransformers(): void
    {
        $resolver = new ListTransformerResolver(new EventDispatcher());

        $values = $resolver->transform(new TransformingDriver(), 'transforming', new SourceStub('from-source'));

        self::assertSame(['title' => 'from-source'], $values);
    }

    public function testReturnsNullWithoutMatchingTransformer(): void
    {
        $resolver = new ListTransformerResolver(new EventDispatcher());

        self::assertNull($resolver->transform(new TransformingDriver(), 'transforming', new \stdClass()));
        self::assertNull($resolver->transform(new TransformerlessDriver(), 'plain', new SourceStub('x')));
    }

    public function testMemoizesMapAndDispatchesEventOncePerDriverClass(): void
    {
        $dispatchedWith = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListTransformerEvent::class,
            static function (ListTransformerEvent $event) use (&$dispatchedWith): void {
                $dispatchedWith[] = $event;
            },
        );

        $resolver = new ListTransformerResolver($dispatcher);
        $driver = new TransformingDriver();

        $resolver->transform($driver, 'transforming', new SourceStub('a'));
        $resolver->transform($driver, 'transforming', new SourceStub('b'));

        self::assertSame(1, $driver->configureCalls);
        self::assertCount(1, $dispatchedWith);
        self::assertSame($driver, $dispatchedWith[0]->driver);
    }

    public function testEventListenersCanAddSourceCapabilities(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListTransformerEvent::class,
            static function (ListTransformerEvent $event): void {
                $event->transformers->for(
                    \stdClass::class,
                    static fn (ConfigBuilder $config, object $source) => $config->set('external', true),
                );
            },
        );

        $resolver = new ListTransformerResolver($dispatcher);

        $values = $resolver->transform(new TransformerlessDriver(), 'plain', new \stdClass());

        self::assertSame(['external' => true], $values);
    }
}

final class SourceStub
{
    public function __construct(
        public readonly string $title,
    ) {}
}

final class TransformingDriver implements ListDriverInterface, TransformerContract
{
    public int $configureCalls = 0;

    public function resolveDcTable(string $type, array $config, array $attributes): string
    {
        return (string) ($config['dc'] ?? '');
    }

    public function configureTransformers(TransformerResolver $resolver): void
    {
        $this->configureCalls++;

        $resolver->for(SourceStub::class, static function (ConfigBuilder $config, SourceStub $source): void {
            $config->set('title', $source->title);
        });
    }
}

final class TransformerlessDriver implements ListDriverInterface
{
    public function resolveDcTable(string $type, array $config, array $attributes): string
    {
        return (string) ($config['dc'] ?? '');
    }
}
