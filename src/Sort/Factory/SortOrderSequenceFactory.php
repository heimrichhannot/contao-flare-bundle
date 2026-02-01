<?php

namespace HeimrichHannot\FlareBundle\Sort\Factory;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Sort\SortOrder;
use HeimrichHannot\FlareBundle\Sort\SortOrderSequence;

class SortOrderSequenceFactory
{
    public function createFromListModel(ListModel $listModel): ?SortOrderSequence
    {
        if (!$listModel->sortSettings) {
            return null;
        }

        if (!$sortSettings = StringUtil::deserialize($listModel->sortSettings, true)) {
            return null;
        }

        try
        {
            return $this->createFromSettings($sortSettings, defaultAlias: ListQueryManager::ALIAS_MAIN);
        }
        catch (FlareException $e)
        {
            return null;
        }
    }

    /**
     * @throws FlareException If the settings are not in the expected format.
     */
    public function createFromSettings(array $settings, ?string $defaultAlias = null): SortOrderSequence
    {
        $orders = [];

        foreach ($settings as $item)
        {
            if (!\is_array($item) || \count($item) < 2 || \count($item) > 3) {
                throw new FlareException('Invalid sort settings format. Expected array of arrays with two or three elements.');
            }

            if (!isset($item['column'], $item['direction'])) {
                throw new FlareException('Invalid sort settings format. Expected array with "column" and "direction" keys (optionally "alias").');
            }

            ['column' => $column, 'direction' => $direction] = $item;

            $alias = $item['alias'] ?? null;

            if (!$alias && \str_contains($column, '.')) {
                [$alias, $column] = \explode('.', $column, 2);
            }

            if (!$alias && $defaultAlias) {
                $alias = $defaultAlias;
            }

            if ($alias && $column && $direction)
            {
                $sortOrder = SortOrder::of($alias, $column, $direction);
                $orders[$sortOrder->key()] = $sortOrder;
            }
        }

        return new SortOrderSequence($orders);
    }
}