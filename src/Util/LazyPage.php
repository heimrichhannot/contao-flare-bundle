<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Util;

use Contao\PageModel;

final class LazyPage
{
    private ?PageModel $page = null;
    private bool $resolved = false;

    public function __construct(
        public readonly int $id,
    ) {}

    public function get(): ?PageModel
    {
        if (!$this->resolved) {
            $this->page = $this->id > 0 ? PageModel::findByPk($this->id) : null;
            $this->resolved = true;
        }

        return $this->page;
    }
}
