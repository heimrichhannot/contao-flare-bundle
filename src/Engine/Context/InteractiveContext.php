<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Sort\SortOrderSequence;
use HeimrichHannot\FlareBundle\Util\LazyPage;
use Symfony\Component\Validator\Constraints as Assert;

class InteractiveContext implements
    ContextInterface,
    Interface\FormContextInterface,
    Interface\PaginatedContextInterface,
    Interface\SortableContextInterface
{
    use ReaderUrlConfigCreatorTrait;

    private readonly LazyPage $formActionPage;

    public static function getContextType(): string
    {
        return 'interactive';
    }

    public function __construct(
        public PaginatorConfig              $paginatorConfig,
        #[Assert\NotBlank] public string    $formName,
        public ?SortOrderSequence           $sortOrderSequence = null,
        #[Assert\PositiveOrZero] public int $contentModelId = 0,
        #[Assert\PositiveOrZero] public int $formActionPageId = 0,
        #[Assert\PositiveOrZero] public int $jumpToReaderPageId = 0,
        #[Assert\NotBlank] public string    $autoItemField = 'id',
        public ?string                      $pageParam = null,
    ) {
        $this->formActionPage = new LazyPage($formActionPageId);
        $this->jumpToReaderPage = new LazyPage($jumpToReaderPageId);
    }

    public function getPaginatorConfig(): PaginatorConfig
    {
        return $this->paginatorConfig;
    }

    public function getFormName(): string
    {
        return $this->formName;
    }

    public function getSortOrderSequence(): ?SortOrderSequence
    {
        return $this->sortOrderSequence;
    }

    public function getPaginatorQueryParameter(): ?string
    {
        return $this->pageParam;
    }

    public function setPaginatorQueryParameter(?string $queryParameter): void
    {
        $this->pageParam = $queryParameter;
    }

    public function createFormActionUrl(): ?string
    {
        return $this->formActionPage->get()?->getAbsoluteUrl();
    }

    public function with(
        ?PaginatorConfig $paginatorConfig = null,
        ?string          $formName = null,
        ?string          $pageParam = null,
    ): self {
        return new self(
            paginatorConfig: $paginatorConfig ?? $this->paginatorConfig,
            formName: $formName ?? $this->formName,
            sortOrderSequence: $this->sortOrderSequence,
            contentModelId: $this->contentModelId,
            formActionPageId: $this->formActionPageId,
            jumpToReaderPageId: $this->jumpToReaderPageId,
            autoItemField: $this->autoItemField,
            pageParam: $pageParam ?? $this->pageParam,
        );
    }
}
