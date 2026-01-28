<?php

namespace HeimrichHannot\FlareBundle\Context\Interface;

use Contao\PageModel;

interface ReaderLinkableInterface
{
    public function getAutoItemField(): string;

    public function getJumpToReaderPage(): ?PageModel;
}