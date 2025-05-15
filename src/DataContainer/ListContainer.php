<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinderInterface;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackContainerInterface;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ListContainer implements FlareCallbackContainerInterface
{
    public const TABLE_NAME = 'tl_flare_list';
    public const CALLBACK_PREFIX = 'list';

    public function __construct(
        private readonly Connection              $connection,
        private readonly FlareCallbackRegistry   $callbackRegistry,
        private readonly ListTypeRegistry        $listTypeRegistry,
        private readonly ResourceFinderInterface $resourceFinder,
        private readonly TranslationManager      $translationManager,
    ) {}

    /* ============================= *
     *  CALLBACK HANDLING            *
     * ============================= */
    // <editor-fold desc="Callback Handling">

    /**
     * @throws \RuntimeException
     */
    public function handleFieldOptions(?DataContainer $dc, string $target): array
    {
        if (!$listModel = $this->getListModelFromDataContainer($dc)) {
            return [];
        }

        $namespace = static::CALLBACK_PREFIX . '.' . $listModel->type;

        $callbacks = $this->callbackRegistry->getSorted($namespace, $target) ?? [];

        return CallbackHelper::firstReturn($callbacks, [], [
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]) ?? [];
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
        if (!$dc->id || !$row = DcaHelper::rowOf($dc)) {
            return;
        }

        if (!$listTypeConfig = $this->listTypeRegistry->get($row['type'] ?: null)) {
            return;
        }

        $service = $listTypeConfig->getService();
        if ($service instanceof DataContainerContract) {
            $expectedDataContainer = $service->getDataContainerName($row, $dc);
        }

        // if data container is not set, use the default data container of the list type
        $expectedDataContainer ??= $listTypeConfig->getDataContainer() ?? '';

        if (!$expectedDataContainer) {
            throw new BadRequestHttpException('No data container found for list type ' . $row['type']);
        }

        if ($expectedDataContainer !== ($row['dc'] ?? null))
        {
            $this->connection
                ->prepare("UPDATE " . self::TABLE_NAME . " SET dc=? WHERE id=?")
                ->executeStatement([$expectedDataContainer, $dc->id]);
        }
    }

    // </editor-fold>

    /* ============================= *
     *  OPTIONS                      *
     * ============================= */
    // <editor-fold desc="Options">

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->listTypeRegistry->all() as $alias => $listTypeConfig)
        {
            $options[$alias] = $this->translationManager->listModel($alias);
        }

        return $options;
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.dc.options')]
    public function getDataContainerOptions(): array
    {
        $options = [];

        $files = $this->resourceFinder->findIn('dca')->name('tl_*.php');

        foreach ($files as $file) {
            $name = $file->getBasename('.php');
            $options[$name] = $name;
        }

        \ksort($options);

        return $options;
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldAutoItem.options')]
    public function getFieldAutoItemOptions(?DataContainer $dc = null): array
    {
        if (empty($row = DcaHelper::rowOf($dc)) || empty($table = $row['dc'])) {
            return ['alias' => 'alias', 'id' => 'id'];
        }

        Controller::loadDataContainer($table);

        $choices = [];

        $fields = \array_keys($GLOBALS['TL_DCA'][$table]['fields'] ?? []);

        foreach ($fields as $field) {
            $choices[$field] = $table . '.' . $field;
        }

        return $choices;
    }

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

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldAutoItem.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldAutoItem.save')]
    public function onLoadField_fieldPublished(mixed $value, DataContainer $dc): string
    {
        if (empty($row = DcaHelper::rowOf($dc)) || empty($table = $row['dc'])) {
            return '';
        }

        return $value ?: DcaHelper::tryGetColumnName($table, 'alias', 'id');
    }

    // </editor-fold>
}