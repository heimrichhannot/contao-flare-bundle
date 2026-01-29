<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\View\ViewInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

abstract class AbstractProjector implements ProjectorInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * Checks if the projector supports the given list context.
     * May be used to overhaul projector logic in a future version. Until then, this method is final.
     */
    abstract public function supports(ContextConfigInterface $config): bool;

    /**
     * {@inheritdoc}
     *
     * @throws FlareException Thrown if the projector does not support the provided list context and configuration.
     */
    abstract public function project(ListSpecification $spec, ContextConfigInterface $config): ViewInterface;

    protected function gatherFilterValues(ListSpecification $spec, array $runtimeValues): array
    {
        $values = [];
        $filterElementRegistry = $this->getFilterElementRegistry();

        foreach ($spec->getFilters()->all() as $key => $definition)
        {
            $element = $filterElementRegistry->get($definition->getType())?->getService();

            if (\array_key_exists($key, $runtimeValues)) {
                $values[$key] = $runtimeValues[$key];
                continue;
            }

            if ($element instanceof IntrinsicValueContract) {
                $values[$key] = $element->getIntrinsicValue($definition);
            }
        }

        return $values;
    }

    protected function getFilterElementRegistry(): FilterElementRegistry
    {
        return $this->container->get(FilterElementRegistry::class);
    }

    public static function getSubscribedServices(): array
    {
        return [
            FilterElementRegistry::class,
        ];
    }
}