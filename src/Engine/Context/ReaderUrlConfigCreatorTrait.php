<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlConfig;

trait ReaderUrlConfigCreatorTrait
{
    private \Closure $jumpToReaderPage;

    final protected function initJumpToReaderPage(): void
    {
        $this->jumpToReaderPage = function (): ?PageModel {
            $pageModel = PageModel::findByPk($this->jumpToReaderPageId);
            $this->jumpToReaderPage = static fn (): ?PageModel => $pageModel;
            return $pageModel;
        };
    }

    protected function getJumpToReaderPage(): ?PageModel
    {
        return ($this->jumpToReaderPage)();
    }

    public function createReaderUrlConfig(): ?ReaderUrlConfig
    {
        if (!$pageModel = $this->getJumpToReaderPage()) {
            return null;
        }

        return new ReaderUrlConfig(readerPage: $pageModel, autoItemField: $this->autoItemField);
    }
}
