<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterValueResolver;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

abstract class AbstractProjector implements ProjectorInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedServices(): array
    {
        return [
            FilterElementRegistry::class,
            FilterValueResolver::class,
            ListQueryDirector::class,
            ProjectorRegistry::class,
            RequestStack::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    abstract public function supports(ListSpecification $list, ContextInterface $context): bool;

    /**
     * {@inheritdoc}
     *
     * The default priority is 0, but can be overriden by subclasses.
     */
    public function priority(ListSpecification $list, ContextInterface $context): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * @throws FlareException Thrown if the projector does not support the provided list context and configuration.
     */
    abstract public function project(ListSpecification $list, ContextInterface $context): ViewInterface;

    public function resolveFilterValues(ListSpecification $spec, array $runtimeValues): array
    {
        return $this->getFilterValueResolver()->resolve($spec, $runtimeValues);
    }

    protected function getFilterElementRegistry(): FilterElementRegistry
    {
        return $this->container->get(FilterElementRegistry::class);
    }

    protected function getFilterValueResolver(): FilterValueResolver
    {
        return $this->container->get(FilterValueResolver::class);
    }

    protected function getListQueryDirector(): ListQueryDirector
    {
        return $this->container->get(ListQueryDirector::class);
    }

    /**
     * @throws FlareException
     */
    protected function getProjectorFor(
        ListSpecification $spec,
        ContextInterface  $config,
        ?array            $exclude = null,
    ): ProjectorInterface {
        try
        {
            return $this->container->get(ProjectorRegistry::class)?->getProjectorFor($spec, $config, $exclude);
        }
        catch (ContainerExceptionInterface $e)
        {
            throw new FlareException(\sprintf('Failed to locate service "%s"', ProjectorRegistry::class),
                previous: $e, source: __METHOD__);
        }
    }

    /**
     * @throws FlareException|FilterException
     */
    protected function createQueryBuilder(ListQueryConfig $config): ?QueryBuilder
    {
        return $this->getListQueryDirector()->createQueryBuilder($config);
    }

    /**
     * @throws FlareException
     */
    protected function getCurrentRequest(): Request
    {
        try
        {
            if (!$request = $this->container->get(RequestStack::class)?->getCurrentRequest()) {
                throw new ServiceNotFoundException('Current request not found in RequestStack');
            }
        }
        catch (ContainerExceptionInterface $e)
        {
            throw new FlareException('Request not available', previous: $e, source: __METHOD__);
        }

        return $request;
    }
}