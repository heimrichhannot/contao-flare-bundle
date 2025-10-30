<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use HeimrichHannot\FlareBundle\Contract\Config\ListItemProviderConfig;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ListItemProviderContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\FilterElement\PublishedElement;
use HeimrichHannot\FlareBundle\List\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\ListItemProvider\DcMultilingualListItemProvider;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;

#[AsListType(alias: self::TYPE, palette: DcMultilingualListType::DEFAULT_PALETTE)]
class DcMultilingualListType extends GenericDataContainerListType implements ListItemProviderContract
{
    public const TYPE = 'flare_generic_dc_multilingual';
    public const DEFAULT_PALETTE = <<<'PALETTE'
        {data_container_legend},dc,fieldAutoItem,dcmultilingual_display;{parent_legend},hasParent;
        {meta_legend},metaTitleFormat,metaDescriptionFormat,metaRobotsFormat
        PALETTE;

    public function __construct(
        HtmlDecoder $htmlDecoder,
        SimpleTokenParser $simpleTokenParser,
        private readonly DcMultilingualListItemProvider $provider
    )
    {
        parent::__construct($htmlDecoder, $simpleTokenParser);
    }


    public function getListItemProvider(ListItemProviderConfig $config): ?ListItemProviderInterface
    {
        return $this->provider;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        return null;
    }


}