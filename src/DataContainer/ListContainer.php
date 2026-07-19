<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\ListDriver\OnSubmitDcContract;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ListContainer
{
    public const TABLE_NAME = 'tl_flare_list';

    public function __construct(
        private readonly Connection         $connection,
        private readonly ListDriverRegistry $listDriverRegistry,
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

    /* ============================= *
     *  CONFIG                       *
     * ============================= */
    // <editor-fold desc="Config">

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     * @throws \Doctrine\DBAL\Exception
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'config.onsubmit')]
    public function onSubmitConfig(DataContainer $dc): void
    {
        if (!$dc->id || !($row = DcaHelper::rowOf($dc)) || !($type = $row['type'] ?? null)) {
            return;
        }

        if (!$service = $this->listDriverRegistry->getService($type)) {
            return;
        }

        $expectedDataContainer = null;

        if (($service instanceof OnSubmitDcContract)
            && !$expectedDataContainer = $service->resolveDcOnSubmit($row, $dc))
        {
            return;
        }

        // if no data container is set, use the default data container of the list type
        if (!$expectedDataContainer) {
            $default = $this->listDriverRegistry->getAttribute($type)?->dataContainer;
            $expectedDataContainer = \is_string($default) ? $default : null;
        }

        if (!$expectedDataContainer) {
            throw new BadRequestHttpException(\sprintf('No data container found for list type "%s".', $type));
        }

        if ($expectedDataContainer !== ($row['dc'] ?? null))
        {
            $qTable = $this->connection->quoteIdentifier(self::TABLE_NAME);
            $stmt = $this->connection->prepare("UPDATE {$qTable} SET {$qTable}.`dc` = :dc WHERE {$qTable}.`id` = :id");
            $stmt->bindValue(':dc', $expectedDataContainer);
            $stmt->bindValue(':id', $dc->id);
            $stmt->executeStatement();
        }
    }

    // </editor-fold>

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
