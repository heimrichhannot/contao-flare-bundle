<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Model;
use Controller;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ConfigInterface;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCallback(table: FilterContainer::TABLE_NAME, target: 'config.onload', priority: 101)]
#[AsCallback(table: ListContainer::TABLE_NAME, target: 'config.onload', priority: 101)]
readonly class AutoTypePalettesCallback
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private ListTypeRegistry      $listTypeRegistry,
        private RequestStack          $requestStack,
    ) {}

    public function __invoke(?DataContainer $dc = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc?->id || $request?->query->get('act') !== 'edit') {
            return;
        }

        $serviceConfigs = match (Model::getClassFromTable($dc->table)) {
            FilterModel::class => $this->filterElementRegistry->all(),
            ListModel::class => $this->listTypeRegistry->all(),
            default => null,
        };

        if (!$serviceConfigs) {
            return;
        }

        foreach ($serviceConfigs as $alias => $config)
        {
            $this->applyPalette($dc, $alias, $config);
        }
    }

    protected function applyPalette(DataContainer $dc, string $alias, ConfigInterface $config): void
    {
        if (!($table = $dc->table) || $alias === 'default' || \str_starts_with($alias, '__')) {
            return;
        }

        $dcaPalettes = &$GLOBALS['TL_DCA'][$table]['palettes'];

        $prefix = $dcaPalettes['__prefix__'] ?? '';
        $suffix = $dcaPalettes['__suffix__'] ?? '';

        $service = $config->getService();

        if ($service instanceof PaletteContract)
        {
            Controller::loadDataContainer(FilterContainer::TABLE_NAME);
            Controller::loadDataContainer(ListContainer::TABLE_NAME);

            if (Model::getClassFromTable($dc->table) === ListModel::class)
            {
                $listModel = ListModel::findByPk($dc->id);
            }
            else // FilterModel
            {
                $filterModel = FilterModel::findByPk($dc->id);
                $listModel = ListModel::findByPk($filterModel?->pid ?: null);
            }

            $paletteConfig = new PaletteConfig(
                alias: $alias,
                dataContainer: $dc,
                prefix: $prefix,
                suffix: $suffix,
                listModel: $listModel ?? null,
                filterModel: $filterModel ?? null
            );

            $palette = $service->getPalette($paletteConfig);

            $prefix = $paletteConfig->getPrefix();
            $suffix = $paletteConfig->getSuffix();
        }

        if (!isset($palette))
        {
            $palette = $config->getPalette();
        }

        $dcaPalettes[$alias] = Str::mergePalettes($prefix, $palette, $suffix);
    }
}