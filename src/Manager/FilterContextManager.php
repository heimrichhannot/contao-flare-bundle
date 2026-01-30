<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Contract\Config\InScopeConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\InScopeContract;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Factory\FilterContextBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;

/**
 * Class FilterContextManager
 *
 * Manages the filter context, including collecting filter contexts.
 */
readonly class FilterContextManager
{
    public function __construct(
        private FilterContextBuilderFactory $contextBuilderFactory,
        private FilterElementRegistry       $filterElementRegistry,
        private ListTypeRegistry            $listTypeRegistry,
    ) {}

    /**
     * Collects filter contexts for a given list model.
     */
    public function collect(ListModel $listModel, ContentContext $context): ?object
    {
        if (!$listModel->id || !$table = $listModel->dc) {
            return null;
        }

        if (!$listTypeConfig = $this->listTypeRegistry->get($listModel->type)) {
            return null;
        }

        if (!$listType = $listTypeConfig->getService()) {
            return null;
        }

        Controller::loadDataContainer($table);

        /** @noinspection ProperNullCoalescingOperatorUsageInspection */
        $filterModels = FilterModel::findByPid($listModel->id, published: true) ?? [];
        $collection = FilterContextCollection::create($listModel);

        $addedFilters = [];

        foreach ($filterModels as $filterModel)
            // Collect filters defined in the backend
        {
            if (!$filterModel->published) {
                continue;
            }

            $filterElementAlias = $filterModel->type;

            if (!$descriptor = $this->filterElementRegistry->get($filterElementAlias)) {
                continue;
            }

            $service = $descriptor->getService();

            // Skip if the filter is not configured for the current context
            if ($service instanceof InScopeContract)
            {
                $inScopeConfig = new InScopeConfig(
                    contentContext: $context,
                    listModel: $listModel,
                    filterModel: $filterModel,
                    descriptor: $descriptor,
                );

                if (!$service->isInScope($inScopeConfig)) {
                    continue;
                }
            }
            /** @mago-expect lint:no-else-clause This else clause is mandatory. */
            elseif (!$descriptor->isAvailableForContext($context))
            {
                continue;
            }

            $filterContext = $this->contextBuilderFactory->create()
                ->setContentContext($context)
                ->setListModel($listModel)
                ->setFilterModel($filterModel)
                ->setFilterElementType($filterElementAlias)
                ->setFilterElementDescriptor($descriptor)
                ->build();

            $collection->add($filterContext);

            $addedFilters[] = $filterElementAlias;
        }

        // Add filters defined by the filter element type
        // -- removed --

        return $collection;
    }

    public function definitionToContext(
        FilterDefinition         $definition,
        ListModel                $listModel,
        ContentContext           $contentContext,
        ?FilterElementDescriptor $descriptor = null,
    ): ?FilterContext {
        if (!$descriptor ??= $this->filterElementRegistry->get($definition->getType())) {
            return null;
        }

        return $this->contextBuilderFactory->create()
            ->setContentContext($contentContext)
            ->setListModel($listModel)
            ->setFilterDefinition($definition)
            ->setFilterElementDescriptor($descriptor)
            ->setFilterElementType($definition->getType())
            ->build();
    }
}