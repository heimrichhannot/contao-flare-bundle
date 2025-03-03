<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ConfigInterface;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Util\Str;

#[AsCallback(table: FilterContainer::TABLE_NAME, target: 'config.onload', priority: 101)]
#[AsCallback(table: ListContainer::TABLE_NAME, target: 'config.onload', priority: 101)]
readonly class AutoTypePalettesCallback
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private ListTypeRegistry      $listTypeRegistry,
    ) {}

    public function __invoke(?DataContainer $dc = null): void
    {
        if (!$dc) return;

        $serviceConfigs = match ($dc->table) {
            FilterContainer::TABLE_NAME => $this->filterElementRegistry->all(),
            ListContainer::TABLE_NAME => $this->listTypeRegistry->all(),
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
        if (!$dc->table || $alias === 'default' || \str_starts_with($alias, '__')) {
            return;
        }

        $dcaPalettes = &$GLOBALS['TL_DCA'][$dc->table]['palettes'];

        $prefix = $dcaPalettes['__prefix__'] ?? '';
        $suffix = $dcaPalettes['__suffix__'] ?? '';

        $service = $config->getService();

        if ($service instanceof PaletteContract)
        {
            $paletteConfig = new PaletteConfig($alias, $dc, $prefix, $suffix);

            $palette = $service->getPalette($paletteConfig);

            $prefix = $paletteConfig->getPrefix();
            $suffix = $paletteConfig->getSuffix();
        }

        if (empty($palette))
        {
            $palette = $config->getPalette();
        }

        $dcaPalettes[$alias] = Str::mergePalettes($prefix, $palette, $suffix);
    }
}