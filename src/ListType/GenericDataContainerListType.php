<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\DataContainer;
use Contao\Message;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageMetaContract;
use HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;
use HeimrichHannot\FlareBundle\Util\Str;

#[AsListType(alias: self::TYPE, palette: self::DEFAULT_PALETTE)]
class GenericDataContainerListType implements DataContainerContract, ReaderPageMetaContract, PaletteContract
{
    public const TYPE = 'flare_generic_dc';
    public const DEFAULT_PALETTE = '{data_container_legend},dc,fieldAutoItem;{parent_legend},hasParent;{meta_legend},metaTitleFormat,metaDescriptionFormat,metaRobotsFormat';

    public function __construct(
        private readonly HtmlDecoder       $htmlDecoder,
        private readonly SimpleTokenParser $simpleTokenParser,
    ) {}

    public function getDataContainerName(array $row, DataContainer $dc): string
    {
        return $row['dc'] ?? '';
    }

    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMetaDto
    {
        $listModel = $config->getListModel();
        $contentModel = $config->getContentModel();
        $model = $config->getModel();

        $pageMeta = new ReaderPageMetaDto();

        $tokens = [];

        foreach ($listModel->row() as $key => $value) {
            if (\is_scalar($value)) {
                $tokens['list.' . $key] = $value;
            }
        }

        foreach ($contentModel->row() as $key => $value) {
            if (\is_scalar($value)) {
                $tokens['ce.' . $key] = $value;
            }
        }

        foreach ($model->row() as $key => $value) {
            if (\is_scalar($value)) {
                $tokens[$key] = $value;
            }
        }

        if ($titleFormat = $listModel->metaTitleFormat)
        {
            $titleFormat = $this->htmlDecoder->inputEncodedToPlainText($titleFormat);
            $title = $this->simpleTokenParser->parse($titleFormat, $tokens, allowHtml: false);
            $title = $this->htmlDecoder->inputEncodedToPlainText($title);
            $pageMeta->setTitle(Str::htmlToMeta($title, flags: \ENT_QUOTES));
        }

        if ($descriptionFormat = $listModel->metaDescriptionFormat)
        {
            $descriptionFormat = $this->htmlDecoder->inputEncodedToPlainText($descriptionFormat);
            $description = $this->simpleTokenParser->parse($descriptionFormat, $tokens, allowHtml: false);
            $description = $this->htmlDecoder->inputEncodedToPlainText($description);
            $pageMeta->setDescription(Str::htmlToMeta($description));
        }

        if ($robotsFormat = $listModel->metaRobotsFormat)
        {
            $robotsFormat = $this->htmlDecoder->inputEncodedToPlainText($robotsFormat);
            $robots = $this->simpleTokenParser->parse($robotsFormat, $tokens, allowHtml: false);
            $pageMeta->setRobots($robots);
        }

        return $pageMeta;
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