<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer;

use Contao\CoreBundle\Framework\ContaoFramework;
use HeimrichHannot\FlareBundle\Contao\Factory\ListCompoundBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The main table of a filter's list as the runtime resolves it: the `dc` of the list built from its stored row
 * ({@see ListCompoundBuilderFactory}), so a driver whose table depends on another config option gets the same table
 * in the backend as on the frontend.
 *
 * Each list is built once per request (memoized by its id, misses too), however many pickers ask. A list that cannot
 * be built — only an `\Exception` is caught; an `\Error` from a driver or a listener propagates, as in
 * {@see \HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter\FieldsOptionsCallbacks} — has no table, so its
 * pickers stay empty silently: the backend inspector reports the broken list.
 *
 * @internal For internal use only. Do not call this class or its methods directly.
 */
final class FilterListTableResolver implements ResetInterface
{
    /** @var array<int, string|null> The resolved tables by list id; null for a missing list or a failed build. */
    private array $tables = [];

    public function __construct(
        private readonly ContaoFramework            $framework,
        private readonly ListCompoundBuilderFactory $listFactory,
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

        $listId = (int) $filterModel->pid;

        if (\array_key_exists($listId, $this->tables)) {
            return $this->tables[$listId];
        }

        return $this->tables[$listId] = $this->resolveList($listId);
    }

    /**
     * Empties the memo, for the next main request.
     */
    public function reset(): void
    {
        $this->tables = [];
    }

    private function resolveList(int $listId): ?string
    {
        $listModel = $this->framework->getAdapter(ListModel::class)->findByPk($listId);

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
