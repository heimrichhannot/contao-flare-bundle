<?php

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

abstract class AbstractProjector implements ProjectorInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * {@inheritdoc}
     */
    abstract public function supports(ContextInterface $config): bool;

    /**
     * {@inheritdoc}
     *
     * The default priority is 0, but can be overriden by subclasses.
     */
    public function priority(ListSpecification $spec, ContextInterface $config): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * @throws FlareException Thrown if the projector does not support the provided list context and configuration.
     */
    abstract public function project(ListSpecification $spec, ContextInterface $config): ViewInterface;

    protected function gatherFilterValues(ListSpecification $spec, array $runtimeValues): array
    {
        $values = [];
        $filterElementRegistry = $this->getFilterElementRegistry();

        foreach ($spec->getFilters()->all() as $key => $filter)
        {
            $element = $filterElementRegistry->get($filter->getType())?->getService();

            if (\array_key_exists($key, $runtimeValues)) {
                $values[$key] = $runtimeValues[$key];
                continue;
            }

            if ($element instanceof IntrinsicValueContract && $filter->isIntrinsic()) {
                $values[$key] = $element->getIntrinsicValue($spec, $filter);
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