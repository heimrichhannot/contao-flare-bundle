<?php

namespace HeimrichHannot\FlareBundle\Context;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Validator\Constraints as Assert;

class InteractiveConfig implements
    ContextConfigInterface,
    Interface\FormContextInterface,
    Interface\PaginatedContextInterface,
    Interface\SortableContextInterface
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

    public function getFormName(): string
    {
        return $this->formName;
    }

    public function getFormActionPage(): int
    {
        return $this->formActionPage;
    }

    public function getPaginatorConfig(): PaginatorConfig
    {
        return $this->paginatorConfig;
    }

    public function getSortDescriptor(): ?SortDescriptor
    {
        return $this->sortDescriptor;
    }

    public function with(?PaginatorConfig $paginatorConfig = null): static
    {
        $clone = clone $this;

        if ($paginatorConfig !== null) {
            $clone->paginatorConfig = $paginatorConfig;
        }

        return $clone;
    }
}