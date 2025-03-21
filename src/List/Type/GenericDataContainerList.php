<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Contract\DataContainerContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Model\ListModel;

#[AsListType(GenericDataContainerList::TYPE)]
class GenericDataContainerList implements PaletteContract, DataContainerContract
{
    public const TYPE = 'flare_generic_dc';

    public function getDataContainerName(array $row, DataContainer $dc): string
    {
        return $row['dc'] ?? '';
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        return '{data_container_legend},dc,fieldAutoItem';
    }
}