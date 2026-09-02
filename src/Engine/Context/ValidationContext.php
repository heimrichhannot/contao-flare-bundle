<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Reader\BackLink;
use HeimrichHannot\FlareBundle\Util\LazyPage;
use Symfony\Component\Validator\Constraints as Assert;

readonly class ValidationContext implements
    ContextInterface,
    Interface\PaginatedContextInterface
{
    use ReaderUrlConfigCreatorTrait;

    private PaginatorConfig $paginatorConfig;
    private LazyPage $jumpToListViewPage;

    public static function getContextType(): string
    {
        return 'validation';
    }

    public function __construct(
        #[Assert\PositiveOrZero] public int $jumpToReaderPageId = 0,
        #[Assert\PositiveOrZero] public int $jumpToListViewPageId = 0,
        #[Assert\NotBlank] public string    $autoItemField = 'id',
        private array                       $filterValues = [],
    ) {
        $this->paginatorConfig = new PaginatorConfig(itemsPerPage: 1);
        $this->jumpToReaderPage = new LazyPage($jumpToReaderPageId);
        $this->jumpToListViewPage = new LazyPage($jumpToListViewPageId);
    }

    public function createBackLink(): ?BackLink
    {
        if (!$pageModel = $this->jumpToListViewPage->get()) {
            return null;
        }

        return BackLink::fromPage($pageModel);
    }

    public function getFilterValues(): array
    {
        return $this->filterValues;
    }

    public function getPaginatorConfig(): PaginatorConfig
    {
        return $this->paginatorConfig;
    }

    public function getPaginatorQueryParameter(): ?string
    {
        return null;
    }

    public function setPaginatorQueryParameter(?string $queryParameter): void
    {
        // ignore
    }

    public function withFilterValues(array $values): self
    {
        return new self(
            jumpToReaderPageId: $this->jumpToReaderPageId,
            jumpToListViewPageId: $this->jumpToListViewPageId,
            autoItemField: $this->autoItemField,
            filterValues: $values,
        );
    }
}
