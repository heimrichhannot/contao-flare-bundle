<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Callback\FlareList;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Contract\ListDriver\OnSubmitDcContract;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Stores the data container a list's driver expects in the list's `dc` column on submit.
 */
final readonly class ConfigOnSubmitCallback
{
    public function __construct(
        private Connection         $connection,
        private ListDriverRegistry $listDriverRegistry,
    ) {}

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[AsCallback(ListContainer::TABLE_NAME, 'config.onsubmit')]
    public function __invoke(DataContainer $dc): void
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
            $qTable = $this->connection->quoteIdentifier(ListContainer::TABLE_NAME);
            $stmt = $this->connection->prepare("UPDATE {$qTable} SET {$qTable}.`dc` = :dc WHERE {$qTable}.`id` = :id");
            $stmt->bindValue(':dc', $expectedDataContainer);
            $stmt->bindValue(':id', $dc->id);
            $stmt->executeStatement();
        }
    }
}
