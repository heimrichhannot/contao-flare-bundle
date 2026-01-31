<?php

namespace HeimrichHannot\FlareBundle\SortDescriptor\Factory;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\SortDescriptor\Order;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

class SortDescriptorFactory
{
    public function createFromListModel(ListModel $listModel): ?SortDescriptor
    {
        if (!$listModel->sortSettings) {
            return null;
        }

        $sortSettings = StringUtil::deserialize($listModel->sortSettings);
        if (!$sortSettings || !\is_array($sortSettings)) {
            return null;
        }

        try
        {
            return $this->createFromSettings($sortSettings);
        }
        catch (FlareException $e)
        {
            return null;
        }
    }

    /**
     * @throws FlareException If the settings are not in the expected format.
     */
    public function createFromSettings(array $settings): SortDescriptor
    {
        $orders = [];
        $columns = [];
        foreach ($settings as $item)
        {
            if (!\is_array($item) || \count($item) !== 2) {
                throw new FlareException('Invalid sort settings format. Expected array of arrays with two elements.', 500);
            }

            if (!isset($item['column'], $item['direction'])) {
                throw new FlareException('Invalid sort settings format. Expected array with "column" and "direction" keys.', 500);
            }

            ['column' => $column, 'direction' => $direction] = $item;

            if (\is_string($column) && \is_string($direction))
            {
                $column = ListQueryManager::ALIAS_MAIN . '.' . \trim($column);

                if (isset($columns[$column])) {
                    throw new FlareException('Duplicate column name found in sort settings: ' . $column, 500);
                }

                $orders[] = Order::of($column, $direction);
                $columns[$column] = true;
            }
        }

        return new SortDescriptor(\array_values($orders));
    }

    /**
     * Creates a sort descriptor from a map of column names and directions.
     *
     * Example:
     * ```
     * $sd = SortDescriptor::fromMap([
     *     'name' => 'asc',
     *     'age' => 'desc'
     * ]);
     * ```
     *
     * @param array $map An array of column names as keys and directions as their values
     *                      (`ASC` or `DESC`, case-insensitive).
     * @return SortDescriptor A new SortDescriptor instance.
     * @throws FlareException If the map is not in the expected format.
     */
    public function createFromMap(array $map): SortDescriptor
    {
        $orders = [];
        foreach ($map as $column => $direction)
        {
            if (!\is_string($column) || !\is_string($direction)) {
                throw new FlareException('Invalid sort settings format. Expected array with string keys and values.', 500);
            }

            $orders[] = Order::of($column, $direction);
        }

        return new SortDescriptor($orders);
    }
}