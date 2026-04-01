<?php

namespace HeimrichHannot\FlareBundle\Engine\Context;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlConfig;

trait ReaderUrlConfigCreatorTrait
{
    public function createReaderUrlConfig(): ?ReaderUrlConfig
    {
        if (!$this->jumpToReaderPageId) {
            return null;
        }

        if (!$pageModel = PageModel::findByPk($this->jumpToReaderPageId)) {
            return null;
        }

        return new ReaderUrlConfig(readerPage: $pageModel, autoItemField: $this->autoItemField);
    }
}