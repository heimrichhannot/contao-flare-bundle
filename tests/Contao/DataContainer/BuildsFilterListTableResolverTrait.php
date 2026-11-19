<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\DataContainer;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterListTableResolver;
use HeimrichHannot\FlareBundle\Contao\Factory\FilterCollector;
use HeimrichHannot\FlareBundle\Contao\Factory\FilterCompoundFactory;
use HeimrichHannot\FlareBundle\Contao\Factory\ListCompoundBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterCompoundFactory as CoreFilterCompoundFactory;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\List\Factory\ListCompoundBuilderFactory as CoreListCompoundBuilderFactory;
use HeimrichHannot\FlareBundle\List\Factory\ListCompoundFactory;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\Fixture\FilterModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A real {@see FilterListTableResolver} over filter and list rows a test describes, building the lists with the real
 * stored-list builder factory. For {@see \Contao\TestCase\ContaoTestCase} subclasses.
 */
trait BuildsFilterListTableResolverTrait
{
    /**
     * The FilterModel adapter finds the filter rows by id, the ListModel adapter the list rows by id (a filter's
     * `pid`). A list model gets the id 0 whatever its key: a list without id has no stored filters to collect (and
     * needs no database).
     *
     * @param array<int, array<string, mixed>> $filters    Filter rows by id.
     * @param array<int, array<string, mixed>> $lists      List rows by id.
     * @param EventDispatcher|null             $dispatcher The dispatcher the lists are built with, for listeners.
     */
    private function createFilterListTableResolver(
        array              $filters,
        array              $lists,
        ListDriverRegistry $drivers,
        ?EventDispatcher   $dispatcher = null,
    ): FilterListTableResolver {
        $filterModels = $this->mockAdapter(['findByPk']);
        $filterModels->method('findByPk')->willReturnCallback(
            static fn (mixed $id): ?FilterModel => isset($filters[$id])
                ? new FilterModelFixture(['id' => $id] + $filters[$id])
                : null,
        );

        $listModels = $this->mockAdapter(['findByPk']);
        $listModels->method('findByPk')->willReturnCallback(
            static fn (mixed $id): ?ListModel => isset($lists[$id])
                ? new ListModelFixture(['id' => 0] + $lists[$id])
                : null,
        );

        $framework = $this->mockContaoFramework([
            FilterModel::class => $filterModels,
            ListModel::class => $listModels,
        ]);

        $dispatcher ??= new EventDispatcher();
        $driverResolver = new ListDriverResolver($drivers);

        $filterCompoundFactory = new FilterCompoundFactory(
            filterCompoundFactory: new CoreFilterCompoundFactory(
                filterElementRegistry: new FilterElementRegistry(),
                filterControlRegistry: new FilterControlRegistry(),
                filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
                filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), $dispatcher),
            ),
            valueProviderRegistry: new ValueProviderRegistry(),
            logger: new NullLogger(),
        );

        return new FilterListTableResolver(
            framework: $framework,
            listFactory: new ListCompoundBuilderFactory(
                builderFactory: new CoreListCompoundBuilderFactory(
                    eventDispatcher: $dispatcher,
                    listDriverResolver: $driverResolver,
                    listCompoundFactory: new ListCompoundFactory(
                        $drivers,
                        new ListOptionsResolver(new SchemaResolver()),
                        new ListTransformerResolver(new SourceTransformer(), $dispatcher),
                        $driverResolver,
                    ),
                ),
                filterCollector: new FilterCollector($dispatcher, $filterCompoundFactory, $drivers, new NullLogger()),
            ),
        );
    }
}
