<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Model;
use Contao\Controller;
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

        [$listModel, $filterModel] = $this->getModelsFromDC($dc);

        if (!$listModel instanceof ListModel) {
            return;
        }

        $config = match (Model::getClassFromTable($dc->table)) {
            FilterModel::class => $this->filterElementRegistry->get($alias = $filterModel?->type),
            ListModel::class => $this->listTypeRegistry->get($alias = $listModel->type),
            default => null,
        };

        if (empty($alias) || !($config instanceof ConfigInterface)) {
            return;
        }

        $this->applyPalette($dc, $alias, $config, $listModel, $filterModel);
    }

    protected function getModelsFromDC(DataContainer $dc): array
    {
        Controller::loadDataContainer(FilterContainer::TABLE_NAME);
        Controller::loadDataContainer(ListContainer::TABLE_NAME);

        $clsModel = Model::getClassFromTable($dc->table);

        switch ($clsModel) {
            case FilterModel::class:
                $filterModel = FilterModel::findByPk($dc->id);
                $listModel = ListModel::findByPk($filterModel?->pid ?: null);
                break;
            case ListModel::class:
                $listModel = ListModel::findByPk($dc->id);
                break;
        }

        return [$listModel ?? null, $filterModel ?? null];
    }

    protected function applyPalette(
        DataContainer   $dc,
        string          $alias,
        ConfigInterface $config,
        ListModel       $listModel,
        ?FilterModel    $filterModel
    ): void {
        if (!($table = $dc->table) || $alias === 'default' || \str_starts_with($alias, '__')) {
            return;
        }

        $dcaPalettes = &$GLOBALS['TL_DCA'][$table]['palettes'];

        $prefix = $dcaPalettes['__prefix__'] ?? '';
        $suffix = $dcaPalettes['__suffix__'] ?? '';

        $service = $config->getService();

        if ($service instanceof PaletteContract)
        {
            $paletteConfig = new PaletteConfig(
                alias: $alias,
                dataContainer: $dc,
                prefix: $prefix,
                suffix: $suffix,
                listModel: $listModel,
                filterModel: $filterModel
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