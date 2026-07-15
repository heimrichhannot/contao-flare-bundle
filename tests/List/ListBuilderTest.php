<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\ListType\BuildListContract;
use HeimrichHannot\FlareBundle\Event\ListBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\ListBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Type\AbstractListType;
use HeimrichHannot\FlareBundle\Model\ListModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListBuilderTest extends TestCase
{
    public function testBuildInvokesTypeHookAndDispatchesEvent(): void
    {
        $dispatchedWith = null;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ListBuildEvent::class, static function (ListBuildEvent $event) use (&$dispatchedWith): void {
            $dispatchedWith = $event->builder;
            $event->builder->addFilter(new Filter(element: 'from_event', alias: 'via_event'));
        });

        $type = new class extends AbstractListType implements BuildListContract {
            public int $buildListCalls = 0;

            public function buildList(ListBuilder $builder): void
            {
                $this->buildListCalls++;
                $builder->addFilter(new Filter(element: 'from_hook', alias: 'via_hook'));
            }
        };

        $builder = $this->createBuilder($dispatcher, typeService: $type);
        $spec = $builder->build();

        self::assertSame(1, $type->buildListCalls);
        self::assertSame($builder, $dispatchedWith);
        self::assertArrayHasKey('via_hook', $spec->filters);
        self::assertArrayHasKey('via_event', $spec->filters);
    }

    public function testFiltersAndTypeCarryOverToTheSpec(): void
    {
        $builder = $this->createBuilder(new EventDispatcher());

        $builder->addFilter(new Filter(element: 'a', alias: 'x'));
        $builder->addFilter(new Filter(element: 'b'));
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
        $type = new class extends AbstractListType {
            protected function transformListModel(ConfigBuilder $config, ListModel $model): void
            {
                $config->set('genericPageMeta', true);
                $config->set('title', 'from-transformer');
            }
        };

        $builder = $this->createBuilder(
            new EventDispatcher(),
            typeService: $type,
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
        EventDispatcher $dispatcher,
        ?object         $typeService = null,
        ?ListModel      $model = null,
    ): ListBuilder {
        return new ListBuilder(
            optionsResolver: new ListOptionsResolver(new SchemaResolver()),
            eventDispatcher: $dispatcher,
            type: 'test_type',
            typeService: $typeService,
            dc: 'tl_test',
            model: $model,
            source: 'tl_flare_list.9',
        );
    }
}
