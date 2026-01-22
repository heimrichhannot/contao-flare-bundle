<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Model\DocumentsFilterModelTrait;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Trait\DynamicPropertiesTrait;

class FilterDefinition
{
    use DocumentsFilterModelTrait;
    use DynamicPropertiesTrait;

    public function __construct(
        private string       $type,
        private string       $title,
        private bool         $intrinsic,
        private ?FilterModel $sourceFilterModel = null,
        private ?string      $filterFormFieldName = null,
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type, ?string &$og = null): static
    {
        $og = $this->type;
        $this->type = $type;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function isIntrinsic(): bool
    {
        return $this->intrinsic;
    }

    public function setIntrinsic(bool $intrinsic): static
    {
        $this->intrinsic = $intrinsic;
        return $this;
    }

    public function getSourceFilterModel(): ?FilterModel
    {
        return $this->sourceFilterModel;
    }

    public function setSourceFilterModel(?FilterModel $sourceFilterModel): static
    {
        $this->sourceFilterModel = $sourceFilterModel;
        return $this;
    }

    public function getFilterFormFieldName(): ?string
    {
        return $this->filterFormFieldName;
    }

    public function setFilterFormFieldName(?string $filterFormFieldName): static
    {
        $this->filterFormFieldName = $filterFormFieldName;
        return $this;
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'type', 'title', 'intrinsic' => true,
            default => $this->issetProperty($name),
        };
    }

    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'type' => $this->setType($value),
            'title' => $this->setTitle($value),
            'intrinsic' => $this->setIntrinsic($value),
            default => $this->setProperty($name, $value),
        };
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'type' => $this->getType(),
            'title' => $this->getTitle(),
            'intrinsic' => $this->isIntrinsic(),
            default => $this->getProperty($name),
        };
    }

    public function getRow(): array
    {
        return \array_merge($this->getProperties(), [
            'title' => $this->title,
            'type' => $this->type,
            'intrinsic' => $this->intrinsic,
        ]);
    }

    public function hash(): string
    {
        return \sha1(\serialize([
            'row' => $this->getRow(),
            'list' => $this->getSourceFilterModel() ? [
                $this->getSourceFilterModel()->id,
                $this->getSourceFilterModel()->type,
            ] : null,
        ]));
    }

    public static function fromFilterModel(FilterModel $filterModel): static
    {
        $self = new static(
            type: $filterModel->type,
            title: $filterModel->title,
            intrinsic: $filterModel->intrinsic,
            sourceFilterModel: $filterModel,
            filterFormFieldName: $filterModel->getFormName(),
        );

        $self->setProperties($filterModel->row());

        return $self;
    }
}