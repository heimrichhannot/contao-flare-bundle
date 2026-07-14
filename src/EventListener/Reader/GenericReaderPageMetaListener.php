<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Reader;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Util\Arr;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
readonly class GenericReaderPageMetaListener
{
    public function __construct(
        private HtmlDecoder       $htmlDecoder,
        private SimpleTokenParser $simpleTokenParser,
    ) {}

    public function __invoke(ReaderPageMetaEvent $event): void
    {
        $list = $event->getList();
        $contentModel = $event->getContentModel();
        $model = $event->getDisplayModel();

        if (!($list->config['genericPageMeta'] ?? false)) {
            return;
        }

        $pageMeta = $event->getPageMeta();

        $titleFormat = $pageMeta->getTitle() ? null : $list->config['metaTitleFormat'];
        $descriptionFormat = $pageMeta->getDescription() ? null : $list->config['metaDescriptionFormat'];
        $robotsFormat = $pageMeta->getRobots() ? null : $list->config['metaRobotsFormat'];

        if (\is_null($titleFormat) && \is_null($descriptionFormat) && \is_null($robotsFormat)) {
            // skip if no data formats are available for the page
            return;
        }

        $tokens = [
            'list.type' => $list->getTypeAlias() ?? $list->type::class,
            'list.dc' => $list->dc,
        ];

        $this->addTokensFromProperties($tokens, $list->config, prefix: 'list');
        $this->addTokensFromProperties($tokens, $contentModel->row(), prefix: 'ce');
        $this->addTokensFromProperties($tokens, $model->row());

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
    }

    private function addTokensFromProperties(array &$tokens, array $properties, ?string $prefix = null): void
    {
        foreach ($properties as $key => $value)
        {
            $path = \is_null($prefix) ? $key : "{$prefix}.{$key}";

            if (\is_array($value))
                // canonical config values are already deserialized
            {
                foreach (Arr::flatten($value, prefix: $path) as $flatKey => $flatValue) {
                    $tokens[$flatKey] = $flatValue;
                }

                continue;
            }

            if (!\is_scalar($value)) {
                continue;
            }

            $tokens[$path] = $value;

            if (\is_array($deserialized = StringUtil::deserialize($value)))
            {
                $flat = Arr::flatten($deserialized, prefix: $path);

                foreach ($flat as $flatKey => $flatValue) {
                    $tokens[$flatKey] = $flatValue;
                }
            }
        }
    }
}