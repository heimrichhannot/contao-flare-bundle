<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\ListType\GenericDataContainerListType;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
readonly class GenericPageMetaListener
{
    public function __construct(
        private HtmlDecoder       $htmlDecoder,
        private SimpleTokenParser $simpleTokenParser,
    ) {}

    public function __invoke(ReaderPageMetaEvent $event): void
    {
        $list = $event->getListSpecification();
        $contentModel = $event->getContentModel();
        $model = $event->getDisplayModel();

        if (!$list->isPageMetaGeneric) {
            return;
        }

        $pageMeta = $event->getPageMeta() ?? new ReaderPageMeta();

        $titleFormat = $pageMeta->getTitle() ? null : $list->metaTitleFormat;
        $descriptionFormat = $pageMeta->getDescription() ? null : $list->metaDescriptionFormat;
        $robotsFormat = $pageMeta->getRobots() ? null : $list->metaRobotsFormat;

        if (\is_null($titleFormat) && \is_null($descriptionFormat) && \is_null($robotsFormat)) {
            // skip if no data formats are available for the page
            return;
        }

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

        if ($titleFormat)
        {
            $titleFormat = $this->htmlDecoder->inputEncodedToPlainText($titleFormat);
            $title = $this->simpleTokenParser->parse($titleFormat, $tokens, allowHtml: false);
            $title = $this->htmlDecoder->inputEncodedToPlainText($title);
            $pageMeta->setTitle(Str::htmlToMeta($title, flags: \ENT_QUOTES));
        }

        if ($descriptionFormat)
        {
            $descriptionFormat = $this->htmlDecoder->inputEncodedToPlainText($descriptionFormat);
            $description = $this->simpleTokenParser->parse($descriptionFormat, $tokens, allowHtml: false);
            $description = $this->htmlDecoder->inputEncodedToPlainText($description);
            $pageMeta->setDescription(Str::htmlToMeta($description));
        }

        if ($robotsFormat)
        {
            $robotsFormat = $this->htmlDecoder->inputEncodedToPlainText($robotsFormat);
            $robots = $this->simpleTokenParser->parse($robotsFormat, $tokens, allowHtml: false);
            $pageMeta->setRobots($robots);
        }

        $event->setPageMeta($pageMeta);
    }
}