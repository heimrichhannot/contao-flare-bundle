<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer;

use Contao\CoreBundle\Framework\ContaoFramework;
use HeimrichHannot\FlareBundle\Contao\Factory\ListCompoundBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

/**
 * The main table of a filter's list as the runtime resolves it: the `dc` of the list built from its stored row
 * ({@see ListCompoundBuilderFactory}), so a driver whose table depends on another config option gets the same table
 * in the backend as on the frontend.
 *
 * @internal For internal use only. Do not call this class or its methods directly.
 */
final readonly class FilterListTableResolver
{
    public function __construct(
        private ContaoFramework            $framework,
        private ListCompoundBuilderFactory $listFactory,
    ) {}

    /**
     * @return string|null Null for a missing filter row or list, or a list that cannot be built.
     */
    public function resolve(int $filterId): ?string
    {
        $filterModel = $this->framework->getAdapter(FilterModel::class)->findByPk($filterId);

        if (!$filterModel instanceof FilterModel) {
            return null;
        }

        $listModel = $this->framework->getAdapter(ListModel::class)->findByPk((int) $filterModel->pid);

        if (!$listModel instanceof ListModel) {
            return null;
        }

        try
        {
            return $this->listFactory->create($listModel)->build()->dc;
        }
        catch (\Exception)
        {
            return null;
        }
    }
}
