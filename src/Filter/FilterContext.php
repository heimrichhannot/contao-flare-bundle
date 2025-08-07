<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Factory\FilterContextBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;

class FilterContext
{
    /**
     * @internal Use {@see FilterContextBuilder} (inject {@see FilterContextBuilderFactory}) to create a new instance.
     */
    public function __construct(
        private readonly ContentContext          $contentContext,
        private readonly ListModel               $listModel,
        private readonly FilterModel             $filterModel,
        private readonly FilterElementDescriptor $filterElementDescriptor,
        private readonly string                  $filterElementAlias,
        private readonly string                  $table,
        private mixed                            $submittedData = null,
    ) {}

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getFilterModel(): FilterModel
    {
        return $this->filterModel;
    }

    public function getDescriptor(): FilterElementDescriptor
    {
        return $this->filterElementDescriptor;
    }

    public function getFilterAlias(): string
    {
        return $this->filterElementAlias;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getSubmittedData(): mixed
    {
        return $this->submittedData;
    }

    public function setSubmittedData(mixed $submittedData): void
    {
        $this->submittedData = $submittedData;
    }
}