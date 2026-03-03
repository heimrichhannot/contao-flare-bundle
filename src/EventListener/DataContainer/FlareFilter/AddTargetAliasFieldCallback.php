<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\EventListener\DataContainer\AutoTypePalettesCallback;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;

/**
 * Callback class that adds a targetAlias field to the filter palette when the filter type declares isTargeted.
 * > Required to load before {@see AutoTypePalettesCallback}, hence the priority.
 *
 * @internal For internal use only. Do not call this class or its methods directly.
 */
#[AsCallback(table: FilterContainer::TABLE_NAME, target: 'config.onload', priority: 120)]
readonly class AddTargetAliasFieldCallback
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
    ) {}

    public function __invoke(?DataContainer $dc = null): void
    {
        if (!$dc?->id) {
            return;
        }

        Controller::loadDataContainer(FilterContainer::TABLE_NAME);

        if (!$filterModel = FilterModel::findByPk($dc?->id)) {
            return;
        }

        if (!$filterModel->type) {
            return;
        }

        if (!$descriptor = $this->filterElementRegistry->get($filterModel->type)) {
            return;
        }

        if (!$descriptor->isTargeted()) {
            return;
        }

        $prefix = &$GLOBALS['TL_DCA'][FilterContainer::TABLE_NAME]['palettes']['__prefix__'];

        $prefix = PaletteManipulator::create()
            ->addField('targetAlias', 'intrinsic')
            ->applyToString('' . $prefix);
    }
}