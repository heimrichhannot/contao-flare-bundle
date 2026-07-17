<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\ListDriver\BuildListContract;
use HeimrichHannot\FlareBundle\Event\ListBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecFactory;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListSpecBuilderTest extends TestCase
{
    public static function filter(string $type, ?string $alias = null): Filter
    {
        static $element = null;

        $element ??= new class implements FilterElementInterface {
            public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

            public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void {}
        };

        return new Filter(element: $element, type: $type, alias: $alias);
    }

    public function testBuildInvokesDriverHookAndDispatchesEvent(): void
    {
        $dispatchedWith = null;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ListBuildEvent::class, static function (ListBuildEvent $event) use (&$dispatchedWith): void {
            $dispatchedWith = $event->builder;
            $event->builder->addFilter(self::filter('from_event', 'via_event'));
        });

        $driver = new class extends AbstractListDriver implements BuildListContract {
            public int $buildListCalls = 0;

            public function buildList(ListSpecBuilder $builder): void
            {
                $this->buildListCalls++;
                $builder->addFilter(ListSpecBuilderTest::filter('from_hook', 'via_hook'));
            }
        };

        $builder = $this->createBuilder($dispatcher, driver: $driver);
        $spec = $builder->build();

        self::assertSame(1, $driver->buildListCalls);
        self::assertSame($builder, $dispatchedWith);
        self::assertArrayHasKey('via_hook', $spec->filters);
        self::assertArrayHasKey('via_event', $spec->filters);
    }

    public function testFiltersDcAndSourceCarryOverToTheSpec(): void
    {
        $builder = $this->createBuilder(new EventDispatcher());

        $builder->addFilter(self::filter('a', 'x'));
        $builder->addFilter(self::filter('b'));
        $builder->removeFilter('x');

        self::assertTrue($builder->hasFilterOfType('b'));
        self::assertFalse($builder->hasFilterOfType('a'));

        $spec = $builder->build();

        self::assertSame($builder->getDriver(), $spec->driver);
        self::assertSame('tl_test', $spec->dc);
        self::assertSame('tl_test', $spec->config['dc']);
        self::assertSame('tl_flare_list.9', $spec->source);
        self::assertArrayHasKey('_generated_0', $spec->filters);
        self::assertArrayNotHasKey('x', $spec->filters);
    }

    public function testModelTransformationAndOverridePrecedence(): void
    {
        $driver = new class extends AbstractListDriver {
            protected function transformListModel(ConfigBuilder $config, ListModel $model): void
            {
                $config->set('genericPageMeta', true);
                $config->set('title', 'from-transformer');
            }
        };

        $builder = $this->createBuilder(
            new EventDispatcher(),
            driver: $driver,
            model: new ListModelStub(['dc' => 'tl_test', 'title' => 'from-model']),
        );

        $builder->set('title', 'from-override');

        $config = $builder->build()->config;

        self::assertSame('tl_test', $config['dc']);            // base transformation
        self::assertTrue($config['genericPageMeta']);          // driver transformer over base
        self::assertSame('from-override', $config['title']);   // explicit override wins
    }

    public function testBuildFailsWithoutAnyDataContainer(): void
    {
        $builder = new ListSpecBuilder(
            specFactory: self::specFactory(),
            transformerResolver: new ListTransformerResolver(new EventDispatcher()),
            eventDispatcher: new EventDispatcher(),
            driver: new class extends AbstractListDriver {},
            source: 'tl_flare_list.9',
        );

        $this->expectException(FlareException::class);
        $builder->build();
    }

    public function testInvalidConfigThrowsWithSourceProvenance(): void
    {
        $builder = $this->createBuilder(new EventDispatcher());
        $builder->set('unknown_key', 1);

        try
        {
            $builder->build();
            self::fail('Expected FlareException.');
        }
        catch (FlareException $e)
        {
            self::assertSame('tl_flare_list.9', $e->getSource());
        }
    }

    private static function specFactory(): ListSpecFactory
    {
        return new ListSpecFactory(new ListDriverRegistry(), new ListOptionsResolver(new SchemaResolver()));
    }

    private function createBuilder(
        EventDispatcher      $dispatcher,
        ?ListDriverInterface $driver = null,
        ?ListModel           $model = null,
    ): ListSpecBuilder {
        return new ListSpecBuilder(
            specFactory: self::specFactory(),
            transformerResolver: new ListTransformerResolver($dispatcher),
            eventDispatcher: $dispatcher,
            driver: $driver ?? new class extends AbstractListDriver {},
            model: $model ?? new ListModelStub(['dc' => 'tl_test']),
            source: 'tl_flare_list.9',
        );
    }
}
