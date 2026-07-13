<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use Contao\PageModel;

readonly class BackLink
{
    public function __construct(
        public string    $url,
        public PageModel $page,
    ) {}

    public static function fromPage(PageModel $page): self
    {
        return new self(url: $page->getAbsoluteUrl(), page: $page);
    }
}
