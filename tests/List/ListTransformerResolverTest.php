<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\ListDriverTransformerEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListTransformerResolverTest extends TestCase
{
    public function testTransformsSourceThroughDriverTransformers(): void
    {
        $resolver = new ListTransformerResolver(new SourceTransformer(), new EventDispatcher());

        $values = $resolver->transform(new TransformingDriver(), 'transforming', new SourceStub('from-source'));

        self::assertSame(['title' => 'from-source'], $values);
    }

    public function testReturnsNullWithoutMatchingTransformer(): void
    {
        $resolver = new ListTransformerResolver(new SourceTransformer(), new EventDispatcher());

        self::assertNull($resolver->transform(new TransformingDriver(), 'transforming', new \stdClass()));
        self::assertNull($resolver->transform(new TransformerlessDriver(), 'plain', new SourceStub('x')));
    }

    public function testFailuresAreWrappedWithTheOrigin(): void
    {
        $resolver = new ListTransformerResolver(new SourceTransformer(), new EventDispatcher());

        try
        {
            $resolver->transform(new FailingDriver(), 'failing', new SourceStub('x'), 'tl_flare_list.3');
            self::fail('Expected FlareException.');
        }
        catch (FlareException $e)
        {
            self::assertStringContainsString(FailingDriver::class, $e->getMessage());
            self::assertSame('tl_flare_list.3', $e->getSource());
            self::assertInstanceOf(\ValueError::class, $e->getPrevious());
        }
    }

    public function testMemoizesMapAndDispatchesEventOncePerDriverClass(): void
    {
        $dispatchedWith = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListDriverTransformerEvent::class,
            static function (ListDriverTransformerEvent $event) use (&$dispatchedWith): void {
                $dispatchedWith[] = $event;
            },
        );

        $resolver = new ListTransformerResolver(new SourceTransformer(), $dispatcher);
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
            ListDriverTransformerEvent::class,
            static function (ListDriverTransformerEvent $event): void {
                $event->transformers->for(
                    \stdClass::class,
                    static fn (ConfigBuilder $config, object $source) => $config->set('external', true),
                );
            },
        );

        $resolver = new ListTransformerResolver(new SourceTransformer(), $dispatcher);

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

    public function configureTransformers(TransformerMap $transformers): void
    {
        $this->configureCalls++;

        $transformers->for(SourceStub::class, static function (ConfigBuilder $config, SourceStub $source): void {
            $config->set('title', $source->title);
        });
    }
}

final class FailingDriver implements ListDriverInterface, TransformerContract
{
    public function resolveDcTable(string $type, array $config, array $attributes): string
    {
        return '';
    }

    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->for(SourceStub::class, static function (): void {
            throw new \ValueError('"x" is not a valid backing value');
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
