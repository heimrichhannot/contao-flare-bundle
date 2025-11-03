<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract;
use HeimrichHannot\FlareBundle\Event\ListFieldOptionsEvent;
use HeimrichHannot\FlareBundle\Registry\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ListContainer implements FlareCallbackContainerInterface
{
    public const TABLE_NAME = 'tl_flare_list';
    public const CALLBACK_PREFIX = 'list';

    public function __construct(
        private readonly Connection               $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FlareCallbackRegistry    $callbackRegistry,
        private readonly ListTypeRegistry         $listTypeRegistry,
    ) {}

    /* ============================= *
     *  CALLBACK HANDLING            *
     * ============================= */
    // <editor-fold desc="Callback Handling">

    public function handleConfigOnLoad(?DataContainer $dc, string $target): void
    {
        if (!$listModel = $this->getListModelFromDataContainer($dc)) {
            return;
        }

        $namespace = static::CALLBACK_PREFIX . '.' . $listModel->type;

        $callbacks = $this->callbackRegistry->getSorted($namespace, $target) ?? [];
        $callbacks = \array_reverse($callbacks);

        CallbackHelper::call($callbacks, [], [
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]);
    }

    /**
     * @throws \RuntimeException
     */
    public function handleFieldOptions(?DataContainer $dc, string $target): array
    {
        if (!$listModel = $this->getListModelFromDataContainer($dc)) {
            return [];
        }

        $event = new ListFieldOptionsEvent(
            dataContainer: $dc,
            listModel: $listModel,
        );

        $this->eventDispatcher->dispatch($event, $target);

        return $event->getOptions();
    }

    /**
     * @throws \RuntimeException
     */
    public function handleLoadField(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        return $this->handleValueCallback($value, $dc, $target);
    }

    /**
     * @throws \RuntimeException
     */
    public function handleSaveField(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        return $this->handleValueCallback($value, $dc, $target);
    }

    /**
     * @throws \RuntimeException
     */
    public function handleValueCallback(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        if (!$listModel = $this->getListModelFromDataContainer($dc)) {
            return $value;
        }

        $namespace =  static::CALLBACK_PREFIX . '.' . $listModel->type;

        $callbacks = $this->callbackRegistry->getSorted($namespace, $target) ?? [];

        return CallbackHelper::firstReturn($callbacks, [$value], [
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]) ?? $value;
    }

    public function getListModelFromDataContainer(?DataContainer $dc): ?ListModel
    {
        if (!$dc?->id) {
            return null;
        }

        return ListModel::findByPk($dc->id);
    }

    // </editor-fold>

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

        if (!$listTypeConfig = $this->listTypeRegistry->get($type)) {
            return;
        }

        $service = $listTypeConfig->getService();

        if (($service instanceof DataContainerContract)
            && !$expectedDataContainer = $service->getDataContainerName($row, $dc))
        {
            return;
        }

        // if no data container is set, use the default data container of the list type
        $expectedDataContainer ??= $listTypeConfig->getDataContainer() ?? '';

        if (!$expectedDataContainer) {
            throw new BadRequestHttpException('No data container found for list type ' . $type);
        }

        if ($expectedDataContainer !== ($row['dc'] ?? null))
        {
            $qTable = $this->connection->quoteIdentifier(self::TABLE_NAME);
            $this->connection
                ->prepare("UPDATE {$qTable} SET {$qTable}.`dc` = ? WHERE {$qTable}.`id` = ?")
                ->executeStatement([$expectedDataContainer, $dc->id]);
        }
    }

    // </editor-fold>

    /**
     * @internal For internal use only. Do not call this method directly.
     *
     * @see contao/dca/tl_flare_list.php -> `$dca['fields']['sortSettings']['fields']['column']['options_callback']`
     */
    public function getFieldOptions_columns(DataContainer $dc): array
    {
        $row = DcaHelper::rowOf($dc);
        return DcaHelper::getFieldOptions($row['dc'] ?? null);
    }

    public function getListedTableName(DataContainer $dc): ?string
    {
        return ($row = DcaHelper::rowOf($dc)) ? ($row['dc'] ?? null) : null;
    }
}