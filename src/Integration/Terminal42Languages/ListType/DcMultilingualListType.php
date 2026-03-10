<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\Terminal42Languages\ListType;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\ListType\AbstractListType;

#[AsListType(type: self::TYPE, palette: self::DEFAULT_PALETTE)]
class DcMultilingualListType extends AbstractListType implements DataContainerContract
{
    public const TYPE = 'flare_generic_dc_multilingual';
    public const DEFAULT_PALETTE = <<<'PALETTE'
        {data_container_legend},dc,fieldAutoItem,dcMultilingual_display;{parent_legend},hasParent;
        {meta_legend},metaTitleFormat,metaDescriptionFormat,metaRobotsFormat
        PALETTE;

    public function __construct(
        private readonly HtmlDecoder       $htmlDecoder,
        private readonly SimpleTokenParser $simpleTokenParser,
    ) {}

    protected function getHtmlDecoder(): HtmlDecoder
    {
        return $this->htmlDecoder;
    }

    protected function getSimpleTokenParser(): SimpleTokenParser
    {
        return $this->simpleTokenParser;
    }

    public function getDataContainerName(array $row, DataContainer $dc): string
    {
        return $row['dc'] ?? '';
    }
}