<?php

namespace HeimrichHannot\FlareBundle\Projector\Config;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Validator\Constraints as Assert;

class InteractiveConfig
{
    public function __construct(
        #[Assert\NotNull]
        public ?PaginatorConfig $paginatorConfig = null,
        public ?SortDescriptor $sortDescriptor = null,
        #[Assert\PositiveOrZero]
        public int $contentModelId = 0,
        #[Assert\PositiveOrZero]
        public int $formActionPage = 0,
        #[Assert\NotBlank]
        public string $formName = '',
    ) {}

    public function getContentModel(): ?ContentModel
    {
        if ($this->contentModelId === 0) {
            return null;
        }

        return ContentModel::findByPk($this->contentModelId);
    }
}