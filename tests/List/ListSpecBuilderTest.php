<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\ListType\BuildListContract;
use HeimrichHannot\FlareBundle\Event\ListBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\ListDriverReference;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListSpecBuilderTest extends TestCase
{
    public function testBuildInvokesTypeHookAndDispatchesEvent(): void
    {
        $dispatchedWith = null;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ListBuildEvent::class, static function (ListBuildEvent $event) use (&$dispatchedWith): void {
            $dispatchedWith = $event->builder;
            $event->builder->addFilter(new Filter(type: 'from_event', alias: 'via_event'));
        });

        $type = new class extends AbstractListDriver implements BuildListContract {
            public int $buildListCalls = 0;

            public function buildList(ListSpecBuilder $builder): void
            {
                $this->buildListCalls++;
                $builder->addFilter(new Filter(type: 'from_hook', alias: 'via_hook'));
            }
        };

        $builder = $this->createBuilder($dispatcher, driver: $type);
        $spec = $builder->build();

        self::assertSame(1, $type->buildListCalls);
        self::assertSame($builder, $dispatchedWith);
        self::assertArrayHasKey('via_hook', $spec->filters);
        self::assertArrayHasKey('via_event', $spec->filters);
    }

    public function testFiltersAndTypeCarryOverToTheSpec(): void
    {
        $builder = $this->createBuilder(new EventDispatcher());

        $builder->addFilter(new Filter(type: 'a', alias: 'x'));
        $builder->addFilter(new Filter(type: 'b'));
        $builder->removeFilter('x');

        self::assertTrue($builder->hasFilterOfType('b'));
        self::assertFalse($builder->hasFilterOfType('a'));

        $spec = $builder->build();

        self::assertSame('test_type', $spec->type);
        self::assertSame('tl_test', $spec->dc);
        self::assertSame('tl_flare_list.9', $spec->source);
        self::assertArrayHasKey('_generated_0', $spec->filters);
        self::assertArrayNotHasKey('x', $spec->filters);
    }

    public function testModelTransformationAndOverridePrecedence(): void
    {
        $type = new class extends AbstractListDriver {
            protected function transformListModel(ConfigBuilder $config, ListModel $model): void
            {
                $config->set('genericPageMeta', true);
                $config->set('title', 'from-transformer');
            }
        };

        $builder = $this->createBuilder(
            new EventDispatcher(),
            driver: $type,
            model: new ListModelStub(['id' => '9', 'title' => 'from-model']),
        );

        $builder->set('title', 'from-override');

        $config = $builder->build()->config;

        self::assertSame(9, $config['id']);                    // base transformation
        self::assertTrue($config['genericPageMeta']);          // type transformer over base
        self::assertSame('from-override', $config['title']);   // explicit override wins
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

    private function createBuilder(
        EventDispatcher      $dispatcher,
        ?ListDriverInterface $driver = null,
        ?ListModel           $model = null,
    ): ListSpecBuilder {
        return new ListSpecBuilder(
            optionsResolver: new ListOptionsResolver(new SchemaResolver()),
            transformerResolver: new ListTransformerResolver($dispatcher),
            eventDispatcher: $dispatcher,
            driverReference: new ListDriverReference(
                type: 'test_type',
                driver: $driver ?? new class implements ListDriverInterface {},
            ),
            dc: 'tl_test',
            model: $model,
            source: 'tl_flare_list.9',
        );
    }
}
