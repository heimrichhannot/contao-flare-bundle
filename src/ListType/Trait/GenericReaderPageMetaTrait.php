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
        $list = $config->getListSpecification();
        $contentModel = $config->getContentModel();
        $model = $config->getDisplayModel();

        $pageMeta = new ReaderPageMetaDto();

        $tokens = [
            'list.type' => $list->type,
            'list.dc' => $list->dc,
        ];

        foreach ($list->getProperties() as $key => $value) {
            if (\is_scalar($value) && $key !== 'type' && $key !== 'dc') {
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

        if ($titleFormat = $list->metaTitleFormat)
        {
            $titleFormat = $this->getHtmlDecoder()->inputEncodedToPlainText($titleFormat);
            $title = $this->getSimpleTokenParser()->parse($titleFormat, $tokens, allowHtml: false);
            $title = $this->getHtmlDecoder()->inputEncodedToPlainText($title);
            $pageMeta->setTitle(Str::htmlToMeta($title, flags: \ENT_QUOTES));
        }

        if ($descriptionFormat = $list->metaDescriptionFormat)
        {
            $descriptionFormat = $this->getHtmlDecoder()->inputEncodedToPlainText($descriptionFormat);
            $description = $this->getSimpleTokenParser()->parse($descriptionFormat, $tokens, allowHtml: false);
            $description = $this->getHtmlDecoder()->inputEncodedToPlainText($description);
            $pageMeta->setDescription(Str::htmlToMeta($description));
        }

        if ($robotsFormat = $list->metaRobotsFormat)
        {
            $robotsFormat = $this->getHtmlDecoder()->inputEncodedToPlainText($robotsFormat);
            $robots = $this->getSimpleTokenParser()->parse($robotsFormat, $tokens, allowHtml: false);
            $pageMeta->setRobots($robots);
        }

        return $pageMeta;
    }
}