<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use HeimrichHannot\FlareBundle\Filter\Collector\ListModelFilterCollector;
use HeimrichHannot\FlareBundle\ListType\ListTypeInterface;
use HeimrichHannot\FlareBundle\List\ListBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates ListBuilders — from a stored tl_flare_list model with its published filters
 * pre-added, or programmatically from a type and data container.
 */
final readonly class ListBuilderFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListModelFilterCollector $filterCollector,
        private ListOptionsResolver      $listOptionsResolver,
        private ListTypeRegistry         $listTypeRegistry,
    ) {}

    public function create(
        ListTypeInterface|string $type,
        string                   $dc,
        ?ListModel               $model = null,
        ?string                  $source = null,
    ): ListBuilder {
        $typeService = $type instanceof ListTypeInterface
            ? $type
            : $this->listTypeRegistry->get($type)?->getService();

        return new ListBuilder(
            optionsResolver: $this->listOptionsResolver,
            eventDispatcher: $this->eventDispatcher,
            type: $type,
            typeService: $typeService,
            dc: $dc,
            model: $model,
            source: $source,
        );
    }

    public function createFromListModel(ListModel $listModel): ListBuilder
    {
        $builder = $this->create(
            type: (string) $listModel->type,
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
