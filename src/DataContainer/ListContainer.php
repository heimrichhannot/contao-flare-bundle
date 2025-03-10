<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\Config\ResourceFinderInterface;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use HeimrichHannot\FlareBundle\Contract\DataContainerContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListCallback;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackContainerInterface;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\MethodInjector;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal For internal use only. API might change without notice.
 */
class ListContainer implements FlareCallbackContainerInterface
{
    public const TABLE_NAME = 'tl_flare_list';

    public function __construct(
        private readonly Connection              $connection,
        private readonly FlareCallbackRegistry   $callbackRegistry,
        private readonly ListTypeRegistry        $listTypeRegistry,
        private readonly ResourceFinderInterface $resourceFinder,
    ) {}

    /**
     * @throws Exception
     */
    #[AsCallback(self::TABLE_NAME, 'config.onsubmit')]
    public function onSubmitConfig(DataContainer $dc): void
    {
        if (!$dc->id || !$row = $dc->activeRecord?->row()) {
            return;
        }

        if (!$listTypeConfig = $this->listTypeRegistry->get($row['type'] ?? null)) {
            return;
        }

        if (!$service = $listTypeConfig->getService()) {
            return;
        }

        if ($service instanceof DataContainerContract) {
            $expectedDataContainer = $service->getDataContainerName($row, $dc);
        }

        // if data container is not set, use the default data container of the list type
        $expectedDataContainer ??= $listTypeConfig->getDataContainer() ?? '';

        if ($expectedDataContainer !== ($row['dc'] ?? null))
        {
            $this->connection
                ->prepare("UPDATE " . self::TABLE_NAME . " SET dc=? WHERE id=?")
                ->executeStatement([$expectedDataContainer, $dc->id]);
        }
    }

    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->listTypeRegistry->all() as $alias => $filterElement)
        {
            $service = $filterElement->getService();
            $options[$alias] = \class_implements($service, TranslatorInterface::class)
                ? $filterElement->getService()->trans($alias)
                : $alias;
        }

        return $options;
    }

    #[AsListCallback('default', 'fields.dc.options')]
    public function getDataContainerOptions(): array
    {
        $choices = [];

        $files = $this->resourceFinder->findIn('dca')->name('tl_*.php');

        foreach ($files as $file) {
            $name = $file->getBasename('.php');
            $choices[$name] = $name;
        }

        \ksort($choices);

        return $choices;
    }

    /**
     * @throws \ReflectionException
     */
    public function getFieldOptions(?DataContainer $dc): array
    {
        if (!$dc?->id || !($listModel = ListModel::findByPk($dc->id))?->type) {
            return [];
        }

        $prefix = 'list.';

        $namespace = $prefix . $listModel->type;
        $target = "fields.{$dc->field}.options";

        $callbacks = \array_merge(
            $this->callbackRegistry->getSorted($namespace, $target) ?? [],
            $this->callbackRegistry->getSorted($prefix . 'default', $target) ?? []
        );

        foreach ($callbacks as $callbackConfig)
        {
            $method = $callbackConfig->getMethod();
            $service = $callbackConfig->getService();

            if (!\method_exists($service, $method)) {
                continue;
            }

            $options = MethodInjector::invoke($service, $method, [
                ListModel::class  => $listModel,
                DataContainer::class  => $dc,
            ]);

            if (isset($options)) {
                return $options;
            }
        }

        return [];
    }

    public function onLoadField(mixed $value, DataContainer $dc): mixed
    {
        // TODO: Implement onLoadField() method.
        return $value;
    }

    public function onSaveField(mixed $value, DataContainer $dc): mixed
    {
        // TODO: Implement onSaveField() method.
        return $value;
    }
}