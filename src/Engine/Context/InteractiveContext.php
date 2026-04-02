<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Sort\SortOrderSequence;
use Symfony\Component\Validator\Constraints as Assert;

class InteractiveContext implements
    ContextInterface,
    Interface\FormContextInterface,
    Interface\PaginatedContextInterface,
    Interface\SortableContextInterface
{
    use ReaderUrlConfigCreatorTrait;

    public static function getContextType(): string
    {
        return 'interactive';
    }

    public function __construct(
        #[Assert\NotNull] public ?PaginatorConfig $paginatorConfig = null,
        public ?SortOrderSequence                 $sortOrderSequence = null,
        #[Assert\PositiveOrZero] public int       $contentModelId = 0,
        #[Assert\PositiveOrZero] public int       $formActionPage = 0,
        #[Assert\NotBlank] public string          $formName = '',
        #[Assert\PositiveOrZero] public int       $jumpToReaderPageId = 0,
        #[Assert\NotBlank] public string          $autoItemField = 'id',
        public ?string                            $pageParam = null,
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

    public function getPaginatorQueryParameter(): ?string
    {
        return $this->pageParam;
    }

    public function getSortOrderSequence(): ?SortOrderSequence
    {
        return $this->sortOrderSequence;
    }

    public function with(
        ?PaginatorConfig $paginatorConfig = null,
        ?string          $formName = null,
        ?string          $pageParam = null,
    ): static {
        $clone = clone $this;

        if ($paginatorConfig !== null) {
            $clone->paginatorConfig = $paginatorConfig;
        }

        if ($formName !== null) {
            $clone->formName = $formName;
        }

        if ($pageParam !== null) {
            $clone->pageParam = $pageParam;
        }

        return $clone;
    }
}