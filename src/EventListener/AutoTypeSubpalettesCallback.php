<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Contao\TypeSubpaletteInterface;

#[AsCallback(table: FilterContainer::TABLE_NAME, target: 'config.onload')]
#[AsCallback(table: ListContainer::TABLE_NAME, target: 'config.onload')]
readonly class AutoTypeSubpalettesCallback
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

            if (class_implements($config, TypeSubpaletteInterface::class))
            {
                $this->applySubpalette($alias, $service, $dc);
            }
        }
    }

    protected function applySubpalette(string $alias, TypeSubpaletteInterface $service, DataContainer $dc): void
    {
        $GLOBALS['TL_DCA'][$dc->table]['subpalettes'][\sprintf('type_%s', $alias)] = \sprintf(
            ';%s;',
            \trim($service->getSubpalette($alias, $dc), ';')
        );
    }
}