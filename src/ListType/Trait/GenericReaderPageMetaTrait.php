<?php

namespace HeimrichHannot\FlareBundle\ListType\Trait;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Util\Str;

trait GenericReaderPageMetaTrait
{
    abstract protected function getHtmlDecoder(): HtmlDecoder;

    abstract protected function getSimpleTokenParser(): SimpleTokenParser;

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
            $titleFormat = $this->getHtmlDecoder()->inputEncodedToPlainText($titleFormat);
            $title = $this->getSimpleTokenParser()->parse($titleFormat, $tokens, allowHtml: false);
            $title = $this->getHtmlDecoder()->inputEncodedToPlainText($title);
            $pageMeta->setTitle(Str::htmlToMeta($title, flags: \ENT_QUOTES));
        }

        if ($descriptionFormat = $listModel->metaDescriptionFormat)
        {
            $descriptionFormat = $this->getHtmlDecoder()->inputEncodedToPlainText($descriptionFormat);
            $description = $this->getSimpleTokenParser()->parse($descriptionFormat, $tokens, allowHtml: false);
            $description = $this->getHtmlDecoder()->inputEncodedToPlainText($description);
            $pageMeta->setDescription(Str::htmlToMeta($description));
        }

        if ($robotsFormat = $listModel->metaRobotsFormat)
        {
            $robotsFormat = $this->getHtmlDecoder()->inputEncodedToPlainText($robotsFormat);
            $robots = $this->getSimpleTokenParser()->parse($robotsFormat, $tokens, allowHtml: false);
            $pageMeta->setRobots($robots);
        }

        return $pageMeta;
    }
}