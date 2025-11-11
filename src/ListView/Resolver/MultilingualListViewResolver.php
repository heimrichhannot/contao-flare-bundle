<?php

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\ListView\ListView;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use Terminal42\ChangeLanguage\PageFinder;

class MultilingualListViewResolver extends ListViewResolver
{
    private ?PageFinder $pageFinder;

    public function getDetailsPageUrl(ListView $dto, int $id): ?string
    {
        $pageFinder = $this->getPageFinder();
        if (null === $pageFinder) {
            return parent::getDetailsPageUrl($dto, $id);
        }

        $listModel = $dto->getListModel();

        if (!$pageId = (int) ($listModel->jumpToReader ?: 0)) {
            return null;
        }

        $autoItemField = $listModel->getAutoItemField();
        $model = $this->manager->getModel(id: $id, listModel: $listModel, contentContext: $dto->getContentContext());

        if (!$autoItem = CallbackHelper::tryGetProperty($model, $autoItemField)) {
            return null;
        }

        if (!$page = PageModel::findByPk($pageId)) {
            throw new FlareException(\sprintf('Details page not found [ID %s]', $pageId), source: __METHOD__);
        }

        $page = $pageFinder->findAssociatedForLanguage($page, $GLOBALS['TL_LANGUAGE']);

        return $page->getAbsoluteUrl('/' . $autoItem);
    }

    private function getPageFinder(): ?PageFinder
    {
        if (!isset($this->pageFinder)) {
            if (class_exists(PageFinder::class)) {
                $this->pageFinder = new PageFinder();
            } else {
                $this->pageFinder = null;
            }
        }

        return $this->pageFinder;
    }

}