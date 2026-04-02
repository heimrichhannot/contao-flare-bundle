<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification;

use HeimrichHannot\FlareBundle\Model\DocumentsFilterModelTrait;
use HeimrichHannot\FlareBundle\Specification\DataSource\FilterDataSourceInterface;

/**
 * @property string $type
 * @property bool $intrinsic
 */
class FilterDefinition
{
    use DocumentsFilterModelTrait;
    use DynamicPropertiesTrait;

    public function __construct(
        private string                     $type,
        private bool                       $intrinsic,
        private ?string                    $alias = null,
        private ?string                    $targetAlias = null,
        private bool                       $isTargetingForced = false,
        private ?FilterDataSourceInterface $dataSource = null,
    ) {
        if (!\is_null($alias)) {
            $this->setAlias($alias);
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function setAlias(?string $alias): static
    {
        if (!\is_null($alias) && !\preg_match('/^\w+$/', $alias)) {
            throw new \InvalidArgumentException(\sprintf('Filter alias "%s" is invalid: must be alphanumeric and may only contain underscores.', $alias));
        }
        $this->alias = $alias;
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

    public function getDataSource(): ?FilterDataSourceInterface
    {
        return $this->dataSource;
    }

    public function setDataSource(?FilterDataSourceInterface $dataSource): static
    {
        $this->dataSource = $dataSource;
        return $this;
    }

    public function setTargetAlias(?string $targetAlias): static
    {
        if (\is_null($targetAlias)) {
            $this->setTargetingForced(false);
        }

        $this->targetAlias = $targetAlias;
        return $this;
    }

    public function getTargetAlias(): ?string
    {
        return $this->targetAlias;
    }

    public function setTargetingForced(bool $isTargetingForced): static
    {
        $this->isTargetingForced = $isTargetingForced;
        return $this;
    }

    public function isTargetingForced(): bool
    {
        return $this->isTargetingForced;
    }

    public function forceTargetAlias(string $targetAlias): static
    {
        return $this
            ->setTargetAlias($targetAlias)
            ->setTargetingForced(true);
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'type', 'intrinsic' => true,
            'alias', 'targetAlias', 'target_alias', 'sourceFilterModel' => $this->__get($name) !== null,
            default => $this->issetProperty($name),
        };
    }

    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'type' => $this->setType($value),
            'intrinsic' => $this->setIntrinsic($value),
            'targetAlias', 'target_alias' => $this->setTargetAlias($value),
            'dataSource', 'sourceFilterModel' => $this->setDataSource($value),
            default => $this->setProperty($name, $value),
        };
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'type' => $this->getType(),
            'intrinsic' => $this->isIntrinsic(),
            'targetAlias', 'target_alias' => $this->getTargetAlias(),
            'dataSource', 'sourceFilterModel' => $this->getDataSource(),
            default => $this->getProperty($name),
        };
    }

    public function getRow(): array
    {
        return \array_merge($this->getProperties(), [
            'type' => $this->type,
            'intrinsic' => $this->intrinsic,
            'targetAlias' => $this->targetAlias,
        ]);
    }

    public function hash(): string
    {
        return \sha1(\serialize([
            'row' => $this->getRow(),
            'filter' => $this->getDataSource() ? [
                'id' => $this->getDataSource()->getFilterProperty('id'),
                'type' => $this->getDataSource()->getFilterProperty('type'),
            ] : null,
        ]));
    }
}