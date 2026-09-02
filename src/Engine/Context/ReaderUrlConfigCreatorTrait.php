<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlConfig;
use HeimrichHannot\FlareBundle\Util\LazyPage;

trait ReaderUrlConfigCreatorTrait
{
    /** Must be initialized by the using class' constructor. */
    private readonly LazyPage $jumpToReaderPage;

    protected function getJumpToReaderPage(): ?PageModel
    {
        return $this->jumpToReaderPage->get();
    }

    public function createReaderUrlConfig(): ?ReaderUrlConfig
    {
        if (!$pageModel = $this->getJumpToReaderPage()) {
            return null;
        }

        return new ReaderUrlConfig(readerPage: $pageModel, autoItemField: $this->autoItemField);
    }
}
