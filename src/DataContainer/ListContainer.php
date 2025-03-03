<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\Config\ResourceFinderInterface;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use HeimrichHannot\FlareBundle\Contract\DataContainerContract;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListContainer
{
    public const TABLE_NAME = 'tl_flare_list';

    public function __construct(
        private readonly Connection $connection,
        private readonly ListTypeRegistry $listTypeRegistry,
        private readonly ResourceFinderInterface $resourceFinder,
    ) {}

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

    #[AsCallback(self::TABLE_NAME, 'fields.dc.options')]
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
}