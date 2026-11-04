<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Event\ListDriverBuildListEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\ValueClassMismatchError;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Factory\ListCompoundFactory;
use HeimrichHannot\FlareBundle\List\ListCompoundBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListCompoundBuilderTest extends TestCase
{
    public static function filter(string $type, string $alias): FilterCompound
    {
        static $element = null;

        $element ??= new class implements FilterElementInterface {
            public function buildEffect(
                FilterEffectBuilderInterface $builder,
                ElementContext               $context,
                ?ValueInterface              $value,
            ): void {}
        };

        return new FilterCompound(
            element: new ElementBinding($element, $type, new ComponentConfig([]), null),
            alias: $alias,
        );
    }

    public function testBuildInvokesDriverHookAndDispatchesEvent(): void
    {
        $dispatchedWith = null;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListDriverBuildListEvent::class,
            static function (ListDriverBuildListEvent $event) use (&$dispatchedWith): void {
                $dispatchedWith = $event->builder;
                $event->builder->addFilter(self::filter('from_event', 'via_event'));
            },
        );

        $driver = new class extends AbstractListDriver {
            public int $buildListCalls = 0;

            public function buildList(ListCompoundBuilder $builder): void
            {
                $this->buildListCalls++;
                $builder->addFilter(ListCompoundBuilderTest::filter('from_hook', 'via_hook'));
            }
        };

        $builder = $this->createBuilder($dispatcher, driver: $driver);
        $list = $builder->build();

        self::assertSame(1, $driver->buildListCalls);
        self::assertSame($builder, $dispatchedWith);
        self::assertArrayHasKey('via_hook', $list->filters);
        self::assertArrayHasKey('via_event', $list->filters);
    }

    public function testListenerSettingAWrongValueFailsWithAnError(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListDriverBuildListEvent::class,
            static function (ListDriverBuildListEvent $event): void {
                $filter = $event->builder->getFilter('x');
                $event->builder->removeFilter('x');
                $event->builder->addFilter($filter->withValue(new KeywordsValue('wrong')), 'x');
            },
        );

        $builder = $this->createBuilder($dispatcher);
        $builder->addFilter(new FilterCompound(
            element: new ElementBinding(new StubFilterElement(), 'stub', new ComponentConfig([]), BoolValue::class),
            alias: 'x',
        ), 'x');

        $this->expectException(ValueClassMismatchError::class);

        $builder->build();
    }

    public function testFiltersDcAndSourceCarryOverToTheList(): void
    {
        $builder = $this->createBuilder(new EventDispatcher());

        $builder->addFilter(new FilterCompound(
            element: new ElementBinding(new StubFilterElement(), 'stub', new ComponentConfig([]), null),
            alias: 'x',
        ));
        $builder->addFilter(self::filter('b', 'y'));
        $builder->addFilter(self::filter('c', 'z'), 'custom');
        $builder->removeFilter('x');
        $builder->addFailure($failure = new FilterFailure('tl_flare_filter.3', 'broken'));

        self::assertTrue($builder->hasFilterInstance(FilterElementInterface::class));
        self::assertFalse($builder->hasFilterInstance(StubFilterElement::class));

        $list = $builder->build();

        self::assertSame($builder->getDriver(), $list->driver);
        self::assertSame('tl_test', $list->dc);
        self::assertSame('tl_test', $list->config['dc']);
        self::assertSame('tl_flare_list.9', $list->source);
        self::assertSame(['y', 'custom'], \array_keys($list->filters));
        self::assertSame([$failure], $list->failures);
    }

    public function testAddFilterRejectsAnExistingKey(): void
    {
        $builder = $this->createBuilder(new EventDispatcher());
        $builder->addFilter(self::filter('a', 'x'), 'key');

        $this->expectException(FlareException::class);
        $builder->addFilter(self::filter('b', 'y'), 'key');
    }

    public function testAddFilterRejectsAnAliasUsedByAnotherFilter(): void
    {
        $builder = $this->createBuilder(new EventDispatcher());
        $builder->addFilter(self::filter('a', 'x'), 'key');

        try
        {
            $builder->addFilter(self::filter('b', 'x'), 'other');
            self::fail('Expected FlareException.');
        }
        catch (FlareException $e)
        {
            self::assertStringContainsString('"x"', $e->getMessage());
        }

        self::assertSame(['key'], \array_keys($builder->getFilters()));
    }

    public function testAddFilterAcceptsAKeyAgainAfterRemoval(): void
    {
        $builder = $this->createBuilder(new EventDispatcher());
        $builder->addFilter(self::filter('a', 'x'));
        $builder->removeFilter('x');
        $builder->addFilter(self::filter('b', 'x'));

        self::assertSame('b', $builder->getFilter('x')?->element->type);
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
        $registry = new ListDriverRegistry();
        $listDriverResolver = new ListDriverResolver($registry);

        $builder = new ListCompoundBuilder(
            listDriverResolver: $listDriverResolver,
            listCompoundFactory: self::listCompoundFactory($registry, $listDriverResolver),
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

    private static function listCompoundFactory(
        ListDriverRegistry $registry,
        ListDriverResolver $listDriverResolver,
        ?EventDispatcher   $dispatcher = null,
    ): ListCompoundFactory {
        return new ListCompoundFactory(
            $registry,
            new ListOptionsResolver(new SchemaResolver()),
            new ListTransformerResolver(new SourceTransformer(), $dispatcher ?? new EventDispatcher()),
            $listDriverResolver,
        );
    }

    private function createBuilder(
        EventDispatcher      $dispatcher,
        ?ListDriverInterface $driver = null,
        ?ListModel           $model = null,
    ): ListCompoundBuilder {
        $registry = new ListDriverRegistry();
        $listDriverResolver = new ListDriverResolver($registry);

        return new ListCompoundBuilder(
            listDriverResolver: $listDriverResolver,
            listCompoundFactory: self::listCompoundFactory($registry, $listDriverResolver, $dispatcher),
            eventDispatcher: $dispatcher,
            driver: $driver ?? new class extends AbstractListDriver {},
            model: $model ?? new ListModelStub(['dc' => 'tl_test']),
            source: 'tl_flare_list.9',
        );
    }
}
