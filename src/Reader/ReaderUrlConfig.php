<?php

namespace HeimrichHannot\FlareBundle\Reader;

use Contao\PageModel;

readonly class ReaderUrlConfig
{
    public function __construct(
        public PageModel $readerPage,
        public string    $autoItemField,
    ) {}
}