<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceDescriptorInterface;
use HeimrichHannot\FlareBundle\Enum\PaletteContainer;
use HeimrichHannot\FlareBundle\Event\PaletteEvent;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class AutoTypePalettesCallback
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FilterElementRegistry    $filterElementRegistry,
        private ListTypeRegistry         $listTypeRegistry,
        private RequestStack             $requestStack,
    ) {}

    #[AsCallback(table: FilterContainer::TABLE_NAME, target: 'config.onload', priority: 101)]
    public function onFilterContainerConfigLoad(?DataContainer $dc = null): void
    {
        $this->onConfigLoad(PaletteContainer::FILTER, $dc);
    }

    #[AsCallback(table: ListContainer::TABLE_NAME, target: 'config.onload', priority: 101)]
    public function onListContainerConfigLoad(?DataContainer $dc = null): void
    {
        $this->onConfigLoad(PaletteContainer::LIST, $dc);
    }

    public function onConfigLoad(PaletteContainer $container, ?DataContainer $dc = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc || !$dc->id || $request?->query->get('act') !== 'edit') {
            return;
        }

        [$listModel, $filterModel] = $this->getModelsFromDC($container, $dc);

        if (!$listModel instanceof ListModel) {
            return;
        }

        $descriptor = match ($container) {
            PaletteContainer::FILTER => $this->filterElementRegistry->get($alias = $filterModel?->type),
            PaletteContainer::LIST => $this->listTypeRegistry->get($alias = $listModel->type),
        };

        if (!isset($alias) || !$alias || !($descriptor instanceof ServiceDescriptorInterface)) {
            return;
        }

        $this->applyPalette($container, $dc, $alias, $descriptor, $listModel, $filterModel);
    }

    protected function getModelsFromDC(PaletteContainer $container, DataContainer $dc): array
    {
        Controller::loadDataContainer(FilterContainer::TABLE_NAME);
        Controller::loadDataContainer(ListContainer::TABLE_NAME);

        switch ($container) {
            case PaletteContainer::FILTER:
                $filterModel = FilterModel::findByPk($dc->id);
                $listModel = ListModel::findByPk($filterModel?->pid ?: null);
                break;
            case PaletteContainer::LIST:
                $listModel = ListModel::findByPk($dc->id);
                break;
        }

        return [$listModel ?? null, $filterModel ?? null];
    }

    protected function applyPalette(
        PaletteContainer           $container,
        DataContainer              $dc,
        string                     $alias,
        ServiceDescriptorInterface $descriptor,
        ListModel                  $listModel,
        ?FilterModel               $filterModel,
    ): void {
        if (!($table = $dc->table) || $alias === 'default' || \str_starts_with($alias, '__')) {
            return;
        }

        $paletteConfigFactory = static fn (string $prefix, string $suffix): PaletteConfig => new PaletteConfig(
            alias: $alias,
            dataContainer: $dc,
            prefix: $prefix,
            suffix: $suffix,
            listModel: $listModel,
            filterModel: $filterModel,
        );

        $dcaPalettes = &$GLOBALS['TL_DCA'][$table]['palettes'];
        $prefix = $dcaPalettes['__prefix__'] ?? '';
        $suffix = $dcaPalettes['__suffix__'] ?? '';

        $service = $descriptor->getService();

        if ($service instanceof PaletteContract)
            // If the service implements PaletteContract, use its getPalette method.
        {
            $paletteConfig = $paletteConfigFactory($prefix, $suffix);

            $palette = $service->getPalette($paletteConfig);

            $prefix = $paletteConfig->getPrefix();
            $suffix = $paletteConfig->getSuffix();
        }

        if (!isset($palette) && $descriptor instanceof PaletteContract)
            // Grab the default palette specified in the AsListType or AsFilterElement attributes.
        {
            $paletteConfig = $paletteConfigFactory($prefix, $suffix);

            $palette = $descriptor->getPalette($paletteConfig);

            $prefix = $paletteConfig->getPrefix();
            $suffix = $paletteConfig->getSuffix();
        }

        ###> <editor-fold desc="###> Trigger PaletteEvent <###">

        $palette ??= null;

        $event = $this->eventDispatcher->dispatch(new PaletteEvent(
            paletteContainer: $container,
            paletteConfig: $paletteConfigFactory($prefix, $suffix),
            palette: $palette,
        ));

        $palette = $event->getPalette();
        $paletteConfig = $event->getPaletteConfig();
        $prefix = $paletteConfig->getPrefix();
        $suffix = $paletteConfig->getSuffix();

        ###< </editor-fold>

        $dcaPalettes[$alias] = Str::mergePalettes($prefix, $palette, $suffix);
    }
}