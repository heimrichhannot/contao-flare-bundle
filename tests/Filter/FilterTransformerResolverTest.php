<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\FilterTransformerEvent;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterTransformerResolverTest extends TestCase
{
    public function testTransformsSourceThroughElementTransformer(): void
    {
        $resolver = new FilterTransformerResolver(new EventDispatcher());
        $element = new TransformingElement();

        $config = $resolver->transform($element, 'transforming', new RowSource(['value' => 'x']));

        self::assertSame(['value' => 'x'], $config);
    }

    public function testReturnsNullWithoutMatchingTransformer(): void
    {
        $resolver = new FilterTransformerResolver(new EventDispatcher());

        self::assertNull($resolver->transform(new TransformingElement(), 'transforming', new \stdClass()));
        self::assertNull($resolver->transform(new PlainTransformerlessElement(), 'plain', new RowSource([])));
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

        $resolver->transform($element, 'transforming', new RowSource([]));
        $resolver->transform($element, 'transforming', new RowSource([]));

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
                    static fn (ConfigBuilder $config, object $source) => $config->set('external', true),
                );
            },
        );

        $resolver = new FilterTransformerResolver($dispatcher);

        $config = $resolver->transform(new PlainTransformerlessElement(), 'plain', new \stdClass());

        self::assertSame(['external' => true], $config);
    }

    public function testTransformsSourceThroughControlTransformerAndDispatchesTheEventForTheControl(): void
    {
        $owners = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            FilterTransformerEvent::class,
            static function (FilterTransformerEvent $event) use (&$owners): void {
                $owners[] = [$event->owner::class, $event->type];
            },
        );

        $resolver = new FilterTransformerResolver($dispatcher);

        $config = $resolver->transform(new TransformingControl(), 'transforming_control', new RowSource(['b' => 2]));

        self::assertSame(['b' => 2], $config);
        self::assertSame([[TransformingControl::class, 'transforming_control']], $owners);
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
    public function configureTransformers(TransformerResolver $resolver): void
    {
        $resolver->for(RowSource::class, static function (ConfigBuilder $config, RowSource $source): void {
            foreach ($source->row as $key => $value) {
                $config->set($key, $value);
            }
        });
    }

    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
    }
}

final class PlainTransformerlessElement implements FilterElementInterface
{
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
    }
}

final class TransformingControl extends AbstractFilterControl
{
    public function configureTransformers(TransformerResolver $resolver): void
    {
        $resolver->for(RowSource::class, static function (ConfigBuilder $config, RowSource $source): void {
            foreach ($source->row as $key => $value) {
                $config->set($key, $value);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void {}

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
    }

    public function decode(mixed $data, FilterScope $scope): ?BoolValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
    {
        return null;
    }
}
