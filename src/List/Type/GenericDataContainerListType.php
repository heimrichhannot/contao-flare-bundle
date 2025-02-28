<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\TypePaletteInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;

#[AsListType(GenericDataContainerListType::TYPE)]
class GenericDataContainerListType extends AbstractListType implements TypePaletteInterface
{
    public const TYPE = 'flare_generic_dc';

    public function getBaseDataContainerName(array $row, DataContainer $dc): string
    {
        return $row['dc'] ?? '';
    }

    public function getPalette(string $alias, DataContainer $dc): string
    {
        return '{data_container_legend},dc';
    }
}