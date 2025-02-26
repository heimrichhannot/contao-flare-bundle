<?php

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DTO\FlareFilterElementDTO;
use HeimrichHannot\FlareBundle\Controller\FilterElement\AbstractFilterElementController;

class FilterElementManager
{
    /**
     * The symfony service tag for filter elements.
     * This is automatically configured for all services with the AsFilterElement attribute.
     */
    public const TAG_FLARE_FILTER_ELEMENT = 'huh.flare.filter_element';

    /**
     * An associative array of aliases and their corresponding FlareFilterElementDTO instances.
     *
     * @var array<string, FlareFilterElementDTO>
     */
    private array $registeredFilterElements = [];

    /**
     * An associative array of service class names and their corresponding instances.
     *
     * @var array<class-string<AbstractFilterElementController|object>, AbstractFilterElementController|object>
     */
    private array $services = [];

    public function __construct(
        iterable $filterElementServices
    ) {
        foreach ($filterElementServices as $service) {
            $this->services[$service::class] = $service;
        }
    }

    /**
     * Returns all services tagged with the filter element tag.
     *
     * @return array<AbstractFilterElementController|object>
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * Registers a class if it is marked with the AsFilterElement attribute.
     *
     * @param class-string<AbstractFilterElementController|object> $className Fully qualified class name.
     * @throws \ReflectionException
     */
    public function registerFilterElement(string $className): void
    {
        $reflection = new \ReflectionClass($className);
        $attributes = $reflection->getAttributes(AsFilterElement::class);
        if (empty($attributes)) {
            return;
        }

        foreach ($attributes as $attribute)
        {
            /** @var AsFilterElement $attributeInstance */
            $attributeInstance = $attribute->newInstance();

            if (isset($this->registeredFilterElements[$attributeInstance->getAlias()])) {
                throw new \InvalidArgumentException(sprintf('Another filter element with the same alias "%s" is already registered.', $attributeInstance->getAlias()));
            }

            $service = $this->getServices()[$className] ?? null;

            if (!$service) {
                throw new \InvalidArgumentException(sprintf('No service found for filter element "%s".', $className));
            }

            $this->registeredFilterElements[$attributeInstance->getAlias()] = new FlareFilterElementDTO(
                $attributeInstance->getAlias(),
                $className,
                $attributeInstance,
                $service
            );
        }
    }

    /**
     * Returns all registered filter elements.
     *
     * @return array<string, FlareFilterElementDTO>
     */
    public function getRegisteredFilterElements(): array
    {
        return $this->registeredFilterElements;
    }

    /**
     * Returns the filter element by class name or alias
     *
     * @param string $alias
     * @return FlareFilterElementDTO|null
     */
    public function getDTO(string $alias): ?FlareFilterElementDTO
    {
        return $this->registeredFilterElements[$alias] ?? null;
    }
}