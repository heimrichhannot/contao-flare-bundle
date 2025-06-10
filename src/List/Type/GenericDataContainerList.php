<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageMetaContract;
use HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Util\Str;

#[AsListType(
    alias: self::TYPE,
    palette: '{data_container_legend},dc,fieldAutoItem;{meta_legend},metaTitleFormat,metaDescriptionFormat,metaRobotsFormat'
)]
class GenericDataContainerList implements DataContainerContract, ReaderPageMetaContract
{
    public const TYPE = 'flare_generic_dc';

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
}