<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

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
        public PaginatorConfig              $paginatorConfig,
        public ?SortOrderSequence           $sortOrderSequence = null,
        #[Assert\NotBlank] public string    $formName,
        #[Assert\PositiveOrZero] public int $contentModelId = 0,
        #[Assert\PositiveOrZero] public int $formActionPage = 0,
        #[Assert\PositiveOrZero] public int $jumpToReaderPageId = 0,
        #[Assert\NotBlank] public string    $autoItemField = 'id',
        public ?string                      $pageParam = null,
    ) {}

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

    public function setPaginatorQueryParameter(?string $queryParameter): void
    {
        $this->pageParam = $queryParameter;
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
        return new self(
            paginatorConfig: $paginatorConfig ?? $this->paginatorConfig,
            sortOrderSequence: $this->sortOrderSequence,
            formName: $formName ?? $this->formName,
            contentModelId: $this->contentModelId,
            formActionPage: $this->formActionPage,
            jumpToReaderPageId: $this->jumpToReaderPageId,
            autoItemField: $this->autoItemField,
            pageParam: $pageParam ?? $this->pageParam
        );
    }
}
