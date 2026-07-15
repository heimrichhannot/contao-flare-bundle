<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use PHPUnit\Framework\TestCase;

final class ListTransformerResolverTest extends TestCase
{
    public function testTransformsSourceThroughDriverTransformers(): void
    {
        $resolver = new ListTransformerResolver();
        $driver = new TransformingDriver();

        $values = $resolver->transform($driver, new SourceStub('from-source'));

        self::assertSame(['title' => 'from-source'], $values);
    }

    public function testMemoizesTransformerMapPerDriverClass(): void
    {
        $resolver = new ListTransformerResolver();
        $driver = new TransformingDriver();

        $resolver->transform($driver, new SourceStub('a'));
        $resolver->transform($driver, new SourceStub('b'));

        self::assertSame(1, $driver->configureCalls);
    }

    public function testReturnsNullWhenNoTransformerMatchesTheSource(): void
    {
        $resolver = new ListTransformerResolver();

        self::assertNull($resolver->transform(new TransformingDriver(), new \stdClass()));
    }

    public function testReturnsNullForDriversWithoutTransformerContract(): void
    {
        $resolver = new ListTransformerResolver();

        self::assertNull($resolver->transform(new \stdClass(), new SourceStub('x')));
    }
}

final class TransformingDriver implements TransformerContract
{
    public int $configureCalls = 0;

    public function configureTransformers(TransformerResolver $resolver): void
    {
        $this->configureCalls++;

        $resolver->for(SourceStub::class, static function (ConfigBuilder $config, object $source): void {
            \assert($source instanceof SourceStub);
            $config->set('title', $source->title);
        });
    }
}

final class SourceStub
{
    public function __construct(
        public readonly string $title,
    ) {}
}
