<?php

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Attribute\AsFlareFilterElement;
use HeimrichHannot\FlareBundle\DTO\FlareFilterElementDTO;

class FlareFilterElementManager
{
    /**
     * The symfony service tag for filter elements.
     * This is automatically configured for all services with the AsFlareFilterElement attribute.
     */
    public const TAG_FLARE_FILTER_ELEMENT = 'huh.flare.filter_element';

    /**
     * An associative array of class names and their corresponding attribute instances.
     *
     * @var array<string, FlareFilterElementDTO>
     */
    private array $filterElements = [];

    /**
     * Registers a class if it is marked with the AsFlareFilterElement attribute.
     *
     * @param string $className Fully qualified class name.
     * @throws \ReflectionException
     */
    public function registerFilterElement(string $className): void
    {
        $reflection = new \ReflectionClass($className);
        $attributes = $reflection->getAttributes(AsFlareFilterElement::class);
        if (empty($attributes)) {
            return;
        }

        foreach ($attributes as $attribute)
        {
            /** @var AsFlareFilterElement $attributeInstance */
            $attributeInstance = $attribute->newInstance();

            if (isset($this->filterElements[$attributeInstance->getAlias()])) {
                throw new \InvalidArgumentException(sprintf('Another filter element with the same alias "%s" is already registered.', $attributeInstance->getAlias()));
            }

            $this->filterElements[$attributeInstance->getAlias()] = new FlareFilterElementDTO(
                $attributeInstance->getAlias(),
                $className,
                $attributeInstance
            );
        }
    }

    /**
     * Returns all registered filter elements.
     *
     * @return array<string, FlareFilterElementDTO>
     */
    public function getFilterElements(): array
    {
        return $this->filterElements;
    }

    /**
     * Returns the filter element by class name or alias
     *
     * @param string $alias
     * @return FlareFilterElementDTO|null
     */
    public function getFilterElement(string $alias): ?FlareFilterElementDTO
    {
        return $this->filterElements[$alias] ?? null;
    }
}