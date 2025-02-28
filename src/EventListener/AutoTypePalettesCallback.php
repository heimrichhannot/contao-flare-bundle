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

        foreach ($serviceConfigs as $alias => $config)
        {
            $service = $config->getService();

            if ($service instanceof TypePaletteInterface)
            {
                $this->applyPalette($alias, $service, $dc);
            }
        }
    }

    protected function applyPalette(string $alias, TypePaletteInterface $service, DataContainer $dc): void
    {
        if ($alias === 'default' || \str_starts_with($alias, '__')) {
            return;
        }

        $palette = $service->getPalette($alias, $dc);

        if ($palette === null) {
            return;
        }

        $dcaPalettes = &$GLOBALS['TL_DCA'][$dc->table]['palettes'];
        $mask = $dcaPalettes['__mask__'] ?? '';

        if (!\str_contains($mask, '__placeholder__')) {
            return;
        }

        $dcaPalettes[$alias] ??= '';
        $reference = &$dcaPalettes[$alias];

        if ($palette instanceof PaletteManipulator)
        {
            $palette = $palette->applyToString('');
        }

        $reference = \str_replace('__placeholder__', $palette, $mask);
    }
}