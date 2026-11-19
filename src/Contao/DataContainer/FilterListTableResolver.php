<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use HeimrichHannot\FlareBundle\List\Factory\ListCompoundFactory;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;

/**
 * The main table of a filter's list, as its list driver resolves it ({@see ListCompoundFactory}), from the stored
 * list row alone — without building the list.
 *
 * @internal For internal use only. Do not call this class or its methods directly.
 */
final readonly class FilterListTableResolver
{
    public function __construct(
        private Connection         $connection,
        private ListDriverRegistry $drivers,
    ) {}

    /**
     * @return string|null Null for a missing filter row, list or driver, or a driver that resolves no table.
     *
     * @throws DbalException
     */
    public function resolve(int $filterId): ?string
    {
        $row = $this->connection->fetchAssociative(
            'SELECT l.type, l.dc FROM tl_flare_filter f INNER JOIN tl_flare_list l ON l.id = f.pid WHERE f.id = ?',
            [$filterId],
        );

        if ($row === false) {
            return null;
        }

        $type = (string) $row['type'];

        if (!$driver = $this->drivers->getService($type)) {
            return null;
        }

        $attributes = $this->drivers->getAttribute($type)->attributes ?? [];

        return $driver->resolveDcTable($type, ['dc' => (string) $row['dc']], $attributes) ?: null;
    }
}
