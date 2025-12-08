<?php

namespace HeimrichHannot\FlareBundle\Dto;

use Contao\Model;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class ReaderContentDto
{
    public function __construct(
        public int|string|null $autoItem,
        public ContentContext  $contentContext,
        public ListModel       $listModel,
        public ?Model          $model,
    ) {}
}