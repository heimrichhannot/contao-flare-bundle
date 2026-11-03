<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer;

use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

class ListContainer
{
    public const TABLE_NAME = 'tl_flare_list';

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function hasFilterConfigured(ListModel $listModel, string $filterType): bool
    {
        $filterTable = FilterModel::getTable();

        $result = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($filterTable)
            ->where('pid = :pid')
            ->andWhere('published = 1')
            ->andWhere('tstamp > 0')
            ->andWhere('type = :type')
            ->setMaxResults(1)
            ->setParameter('pid', $listModel->id)
            ->setParameter('type', $filterType)
            ->executeQuery();

        return (bool) $result->rowCount();
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     *
     * @see contao/dca/tl_flare_list.php -> `$dca['fields']['sortSettings']['fields']['column']['options_callback']`
     */
    public function getFieldOptions_sortSettings(DataContainer $dc): array
    {
        $row = DcaHelper::rowOf($dc);
        return DcaHelper::getFieldOptions($row['dc'] ?? null, alias: TableAliasRegistry::ALIAS_MAIN);
    }

    public function getListedTableName(DataContainer $dc): ?string
    {
        return ($row = DcaHelper::rowOf($dc)) ? ($row['dc'] ?? null) : null;
    }
}
