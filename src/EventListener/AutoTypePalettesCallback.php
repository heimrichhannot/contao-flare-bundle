<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Contao\TypePaletteInterface;

#[AsCallback(table: FilterContainer::TABLE_NAME, target: 'config.onload')]
#[AsCallback(table: ListContainer::TABLE_NAME, target: 'config.onload')]
readonly class AutoTypePalettesCallback
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private ListTypeRegistry      $listTypeRegistry,
    ) {}

    public function __invoke(?DataContainer $dc = null): void
    {
        $serviceConfigs = match ($dc->table) {
            FilterContainer::TABLE_NAME => $this->filterElementRegistry->all(),
            ListContainer::TABLE_NAME => $this->listTypeRegistry->all(),
            default => null,
        };

        if (!$serviceConfigs) {
            return;
        }

        \dump('DataContainer: ' . $dc->table);

        foreach ($serviceConfigs as $alias => $config)
        {
            $service = $config->getService();

            if (!$service instanceof TypePaletteInterface) {
                continue;
            }

            $palette = $service->getPalette($alias, $dc);

            if ($palette === null) {
                continue;
            }

            if ($palette instanceof PaletteManipulator)
            {
                $palette = $palette->applyToString('');
            }

            $this->applyPalette($dc->table, $alias, $palette);
        }
    }

    protected function applyPalette(string $table, string $name, string $palette): void
    {
        if (!$table || $name === 'default' || \str_starts_with($name, '__')) {
            return;
        }

        $dcaPalettes = &$GLOBALS['TL_DCA'][$table]['palettes'];
        $mask = $dcaPalettes['__mask__'] ?? '__insert__';

        if (!\str_contains($mask, '__insert__')) {
            return;
        }

        $dcaPalettes[$name] = \str_replace('__insert__', $palette, $mask);
    }
}