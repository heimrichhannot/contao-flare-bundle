<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Factory\FilterContextBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use Symfony\Component\Form\FormInterface;

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
        private readonly string                  $filterElementType,
        private readonly string                  $table,
        private ?FormInterface                   $formField = null,
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

    public function getFilterType(): string
    {
        return $this->filterElementType;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getFormField(): ?FormInterface
    {
        return $this->formField;
    }

    public function setFormField(?FormInterface $formField): void
    {
        $this->formField = $formField;
    }

    /**
     * Get the submitted data from the form field.
     */
    public function getFormData(): mixed
    {
        return $this->getFormField()?->getData();
    }

    /**
     * @deprecated Use {@see self::getFormData()} instead.
     */
    public function getSubmittedData(): mixed
    {
        return $this->getFormData();
    }
}