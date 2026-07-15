<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use HeimrichHannot\FlareBundle\Filter\Collector\ListModelFilterCollector;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\List\Type\ListDriverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates ListBuilders — from a stored tl_flare_list model with its published filters
 * pre-added, or programmatically from a type and data container.
 */
final readonly class ListSpecBuilderFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListModelFilterCollector $filterCollector,
        private ListOptionsResolver      $listOptionsResolver,
        private ListTransformerResolver  $listTransformerResolver,
        private ListDriverResolver       $listDriverResolver,
    ) {}

    public function create(
        ListDriverInterface|string $driver,
        string                     $dc,
        ?ListModel                 $model = null,
        ?string                    $source = null,
    ): ListSpecBuilder {
        return new ListSpecBuilder(
            optionsResolver: $this->listOptionsResolver,
            transformerResolver: $this->listTransformerResolver,
            eventDispatcher: $this->eventDispatcher,
            driverReference: $this->listDriverResolver->resolve($driver),
            dc: $dc,
            model: $model,
            source: $source,
        );
    }

    public function createFromListModel(ListModel $listModel): ListSpecBuilder
    {
        $builder = $this->create(
            driver: (string) $listModel->type,
            dc: (string) $listModel->dc,
            model: $listModel,
            source: $listModel::getTable() . '.' . $listModel->id,
        );

        foreach ($this->filterCollector->collect($listModel) ?? [] as $key => $filter) {
            $builder->addFilter($filter, (string) $key);
        }

        return $builder;
    }
}
