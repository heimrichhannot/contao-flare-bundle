<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\ListTransformerEvent;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\List\Type\ListDriverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListTransformerResolverTest extends TestCase
{
    public function testTransformsSourceThroughDriverTransformers(): void
    {
        $resolver = new ListTransformerResolver(new EventDispatcher());
        $driver = new TransformingDriver();

        $values = $resolver->transform($driver, 'test', new SourceStub('from-source'));

        self::assertSame(['title' => 'from-source'], $values);
    }

    public function testReturnsNullWithoutMatchingTransformer(): void
    {
        $resolver = new ListTransformerResolver(new EventDispatcher());

        self::assertNull($resolver->transform(new TransformingDriver(), 'test', new \stdClass()));
        self::assertNull($resolver->transform(new TransformerlessDriver(), 'test', new SourceStub('x')));
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

        $resolver->transform($driver, 'test', new SourceStub('a'));
        $resolver->transform($driver, 'test', new SourceStub('b'));

        self::assertSame(1, $driver->configureCalls);
        self::assertCount(1, $dispatchedWith);
        self::assertSame($driver, $dispatchedWith[0]->typeService);
        self::assertSame('test', $dispatchedWith[0]->type);
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

        $values = $resolver->transform(new TransformerlessDriver(), 'test', new \stdClass());

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
}
