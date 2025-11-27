<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\DataContainer;
use Contao\Message;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\ListType\Trait\GenericReaderPageMetaTrait;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;

#[AsListType(alias: self::TYPE, palette: self::DEFAULT_PALETTE)]
class GenericDataContainerListType extends AbstractListType implements DataContainerContract
{
    use GenericReaderPageMetaTrait;

    public const TYPE = 'flare_generic_dc';
    public const DEFAULT_PALETTE = <<<'PALETTE'
        {data_container_legend},dc,fieldAutoItem;{parent_legend},hasParent;
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

    public function getPalette(PaletteConfig $config): ?string
    {
        $listModel = $config->getListModel();

        if (!$listModel->hasParent) {
            return null;
        }

        $pm = PaletteManipulator::create()
            ->addField('fieldPid', 'parent_legend', PaletteManipulator::POSITION_APPEND)
            ->addField('whichPtable', 'parent_legend', PaletteManipulator::POSITION_APPEND)
        ;

        $table = $listModel->dc;

        $inferrer = new PtableInferrer($listModel, $listModel);

        try
        {
            $ptable = $inferrer->explicit(true);

            Message::addInfo(match (true) {
                $inferrer->isAutoInferable() && $ptable => \sprintf('Parent table of "%s.%s" inferred as "%s"', $table, $listModel->fieldPid, $ptable),
                $inferrer->isAutoDynamicPtable() => \sprintf('Parent table of "%s" can be inferred dynamically', $table),
                default => \sprintf('Parent table cannot be inferred on "%s.%s"', $table, $listModel->fieldPid)
            });
        }
        catch (InferenceException $e)
        {
            Message::addError($e->getMessage());
        }

        if (!$inferrer->isAutoInferable())
        {
            $listModel->whichPtable_disableAutoOption();
        }

        return $pm->applyToString(self::DEFAULT_PALETTE);
    }
}