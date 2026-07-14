<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerBuilder;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\FilterTransformerEvent;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormBuilderInterface;

final class FilterTransformerResolverTest extends TestCase
{
    public function testTransformsSourceThroughElementTransformer(): void
    {
        $resolver = new FilterTransformerResolver(new EventDispatcher());
        $element = new TransformingElement();

        $config = $resolver->transform($element, 'test', new RowSource(['value' => 'x']));

        self::assertSame(['value' => 'x'], $config);
    }

    public function testReturnsNullWithoutMatchingTransformer(): void
    {
        $resolver = new FilterTransformerResolver(new EventDispatcher());

        self::assertNull($resolver->transform(new TransformingElement(), 'test', new \stdClass()));
        self::assertNull($resolver->transform(new PlainTransformerlessElement(), 'test', new RowSource([])));
    }

    public function testMemoizesBuilderAndDispatchesEventOncePerElementClass(): void
    {
        $dispatched = 0;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(FilterTransformerEvent::class, static function () use (&$dispatched): void {
            $dispatched++;
        });

        $resolver = new FilterTransformerResolver($dispatcher);
        $element = new TransformingElement();

        $resolver->transform($element, 'test', new RowSource([]));
        $resolver->transform($element, 'test', new RowSource([]));

        self::assertSame(1, $dispatched);
    }

    public function testEventListenersCanAddSourceCapabilities(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            FilterTransformerEvent::class,
            static function (FilterTransformerEvent $event): void {
                $event->transformers->for(
                    \stdClass::class,
                    static fn (object $source, ConfigBuilder $config) => $config->set('external', true),
                );
            },
        );

        $resolver = new FilterTransformerResolver($dispatcher);

        $config = $resolver->transform(new PlainTransformerlessElement(), 'test', new \stdClass());

        self::assertSame(['external' => true], $config);
    }
}

final class RowSource
{
    public function __construct(
        public array $row = [],
    ) {}
}

final class TransformingElement implements FilterElementInterface, TransformerContract
{
    public function configureTransformers(TransformerBuilder $transformers): void
    {
        $transformers->for(RowSource::class, static function (RowSource $source, ConfigBuilder $config): void {
            foreach ($source->row as $key => $value) {
                $config->set($key, $value);
            }
        });
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
    }
}

final class PlainTransformerlessElement implements FilterElementInterface
{
    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
    }
}
