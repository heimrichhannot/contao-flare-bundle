<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Factory;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory as CoreListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\Model\ListModel;

/**
 * Creates ListSpecBuilders from stored tl_flare_list models, with their published filters and filter failures
 * pre-added.
 */
final readonly class ListSpecBuilderFactory
{
    public function __construct(
        private CoreListSpecBuilderFactory $builderFactory,
        private FilterCollector            $filterCollector,
    ) {}

    /**
     * @throws FlareException In case the list driver cannot be resolved.
     */
    public function create(ListModel $listModel): ListSpecBuilder
    {
        $builder = $this->builderFactory->create(
            driver: (string) $listModel->type,
            model: $listModel,
            source: $listModel::getTable() . '.' . $listModel->id,
        );

        if (!$collected = $this->filterCollector->collect($listModel)) {
            return $builder;
        }

        foreach ($collected->filters as $key => $filter) {
            $builder->addFilter($filter, (string) $key);
        }

        foreach ($collected->failures as $failure) {
            $builder->addFailure($failure);
        }

        return $builder;
    }
}
