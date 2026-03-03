<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader\Factory;

use Contao\CoreBundle\String\HtmlDecoder;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageMetaContract;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Util\Str;

readonly class ReaderPageMetaFactory
{
    public function __construct(
        private HtmlDecoder              $htmlDecoder,
        private ListTypeRegistry         $listTypeRegistry,
    ) {}

    /**
     * @throws FlareException If no list type config is registered for the given list model type.
     */
    public function create(ReaderPageMetaConfig $config): ReaderPageMeta
    {
        $listType = $config->getListSpecification()->type;

        if (!$listTypeConfig = $this->listTypeRegistry->get($listType))
        {
            throw new FlareException(
                \sprintf('No list type config registered for type "%s".', $listType),
                source: __METHOD__
            );
        }

        $service = $listTypeConfig->getService();

        if ($service instanceof ReaderPageMetaContract)
        {
            $pageMeta = $service->getReaderPageMeta($config);
        }

        $pageMeta ??= new ReaderPageMeta();

        if (!$pageMeta->getTitle())
        {
            $model = $config->getDisplayModel();

            $pageMeta->setTitle($this->htmlDecoder->inputEncodedToPlainText(
                (string) (
                    (Str::formatHeadline($model->headline) ?: null)
                    ?? $model->title
                    ?? $model->question
                    ?? $model->name
                    ?? $model->alias
                    ?? $model->id
                ) ?: ''
            ));
        }

        return $pageMeta;
    }
}