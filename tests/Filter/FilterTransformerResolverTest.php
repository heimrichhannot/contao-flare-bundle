<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\FilterControlConfigureTransformersEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementConfigureTransformersEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterTransformerResolverTest extends TestCase
{
    public function testTransformsSourceThroughElementTransformer(): void
    {
        $resolver = new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher());
        $element = new TransformingElement();

        $config = $resolver->transform($element, 'transforming', new RowSource(['value' => 'x']));

        self::assertSame(['value' => 'x'], $config);
    }

    public function testReturnsNullWithoutMatchingTransformer(): void
    {
        $resolver = new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher());

        self::assertNull($resolver->transform(new TransformingElement(), 'transforming', new \stdClass()));
        self::assertNull($resolver->transform(new PlainTransformerlessElement(), 'plain', new RowSource([])));
    }

    public function testMemoizesBuilderAndDispatchesEventOncePerElementClass(): void
    {
        $dispatched = 0;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(FilterElementConfigureTransformersEvent::class, static function () use (&$dispatched): void {
            $dispatched++;
        });

        $resolver = new FilterTransformerResolver(new SourceTransformer(), $dispatcher);
        $element = new TransformingElement();

        $resolver->transform($element, 'transforming', new RowSource([]));
        $resolver->transform($element, 'transforming', new RowSource([]));

        self::assertSame(1, $dispatched);
    }

    public function testEventListenersCanAddSourceCapabilities(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            FilterElementConfigureTransformersEvent::class,
            static function (FilterElementConfigureTransformersEvent $event): void {
                $event->transformers->set(
                    \stdClass::class,
                    static fn (ConfigBuilder $config, object $source) => $config->set('external', true),
                );
            },
        );

        $resolver = new FilterTransformerResolver(new SourceTransformer(), $dispatcher);

        $config = $resolver->transform(new PlainTransformerlessElement(), 'plain', new \stdClass());

        self::assertSame(['external' => true], $config);
    }

    public function testTransformsSourceThroughControlTransformerAndDispatchesTheEventForTheControl(): void
    {
        $owners = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            FilterControlConfigureTransformersEvent::class,
            static function (FilterControlConfigureTransformersEvent $event) use (&$owners): void {
                $owners[] = [$event->control::class, $event->type];
            },
        );
        $dispatcher->addListener(FilterElementConfigureTransformersEvent::class, static function (): void {
            self::fail('A control must not dispatch the element event.');
        });

        $resolver = new FilterTransformerResolver(new SourceTransformer(), $dispatcher);

        $config = $resolver->transform(new TransformingControl(), 'transforming_control', new RowSource(['b' => 2]));

        self::assertSame(['b' => 2], $config);
        self::assertSame([[TransformingControl::class, 'transforming_control']], $owners);
    }

    public function testFailuresAreWrappedInAFilterExceptionWithTheOrigin(): void
    {
        $resolver = new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher());

        try
        {
            $resolver->transform(new FailingElement(), 'failing', new RowSource(), 'tl_flare_filter.42');
            self::fail('Expected FilterException.');
        }
        catch (FilterException $e)
        {
            self::assertStringContainsString(FailingElement::class, $e->getMessage());
            self::assertSame('tl_flare_filter.42', $e->getSource());
            self::assertSame(FailingElement::class . '::configureTransformers', $e->getMethod());
        }
    }
}

final class FailingElement implements FilterElementInterface, TransformerContract
{
    public function configureTransformers(TransformerMap $transformers): void
    {
        throw new \RuntimeException('broken map');
    }

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
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
    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->set(RowSource::class, static function (ConfigBuilder $config, RowSource $source): void {
            foreach ($source->row as $key => $value) {
                $config->set($key, $value);
            }
        });
    }

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
    }
}

final class PlainTransformerlessElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
    }
}

final class TransformingControl extends AbstractFilterControl
{
    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->set(RowSource::class, static function (ConfigBuilder $config, RowSource $source): void {
            foreach ($source->row as $key => $value) {
                $config->set($key, $value);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void {}

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
    {
    }

    public function decode(mixed $data, ControlScope $scope): ?BoolValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}
