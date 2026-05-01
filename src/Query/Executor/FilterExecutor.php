<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query\Executor;

use HeimrichHannot\FlareBundle\Event\FilterElementBuiltEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementBuildingEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterCall;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementInterface;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterExecutor
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private FilterElementRegistry     $filterElementRegistry,
        private FilterQueryBuilderFactory $filterQueryBuilderFactory,
        private FilterTypeRegistry        $filterTypeRegistry,
    ) {}

    /**
     * @return FilterQueryBuilder[]
     *
     * @throws AbortFilteringException
     * @throws FilterException
     * @throws FlareException
     */
    public function invokeFilters(ListQueryConfig $options): array
    {
        $list = $options->list;
        $context = $options->context;

        $filterQueryBuilders = [];

        /**
         * @var int|string $key
         * @var ConfiguredFilter $filter
         */
        foreach ($list->getFilters()->all() as $key => $filter)
        {
            $invocation = new FilterInvocation(
                filter: $filter,
                list: $list,
                context: $context,
                value: $options->filterValues[$key] ?? null,
            );

            if (!$builders = $this->invokeFilter($invocation)) {
                continue;
            }

            \array_push($filterQueryBuilders, ...$builders);
        }

        return $filterQueryBuilders;
    }

    /**
     * @throws AbortFilteringException
     * @throws FilterException
     * @throws FlareException
     */
    /**
     * @return FilterQueryBuilder[]
     */
    public function invokeFilter(FilterInvocation $invocation): array
    {
        if (!Str::isValidSqlName($table = $invocation->list->dc))
        {
            throw new FlareException(\sprintf(
                '[FLARE] ListSpecification data container cannot be used as SQL table identifier: "%s"',
                $table
            ), method: __METHOD__);
        }

        $filter = $invocation->filter;
        $context = $invocation->context;

        if (!$filterElementDescriptor = $this->filterElementRegistry->get($filter->getElementType())) {
            return [];
        }

        $filterElement = $filterElementDescriptor->getService();
        if (!$filterElement instanceof FilterElementInterface) {
            return [];
        }

        $targetAlias = TableAliasRegistry::ALIAS_MAIN;
        if ($filterElementDescriptor->isTargeted() || $filter->isTargetingForced()) {
            $targetAlias = $filter->getTargetAlias() ?: TableAliasRegistry::ALIAS_MAIN;
        }

        $builder = new FilterBuilder($this->filterTypeRegistry, $targetAlias);

        $event = $this->eventDispatcher->dispatch(new FilterElementBuildingEvent(
            invocation: $invocation,
            context: $context,
            builder: $builder,
            shouldBuild: true,
        ));

        if (!$event->shouldBuild()) {
            return [];
        }

        try
        {
            $filterElement->buildFilter($builder, $invocation);
        }
        catch (AbortFilteringException $e)
        {
            throw $e;
        }
        catch (FilterException $e)
        {
            throw $this->createCallbackException($e, $filter, $filterElement);
        }
        catch (\Throwable $e)
        {
            throw new FilterException($e->getMessage(), code: $e->getCode(), previous: $e, method: __METHOD__);
        }

        $this->eventDispatcher->dispatch(new FilterElementBuiltEvent($invocation, $builder));

        return $this->buildQueryBuilders($builder->all(), $filter, $filterElement);
    }

    /**
     * @param FilterCall[] $calls
     * @return FilterQueryBuilder[]
     */
    private function buildQueryBuilders(array $calls, ConfiguredFilter $filter, object $filterElement): array
    {
        $filterQueryBuilders = [];

        foreach ($calls as $call)
        {
            $filterQueryBuilder = $this->filterQueryBuilderFactory->create($call->targetAlias);

            try
            {
                $call->type->buildQuery($filterQueryBuilder, $call->options);
            }
            catch (AbortFilteringException $e)
            {
                throw $e;
            }
            catch (FilterException $e)
            {
                throw $this->createCallbackException($e, $filter, $call->type);
            }
            catch (\Throwable $e)
            {
                throw new FilterException($e->getMessage(), code: $e->getCode(), previous: $e, method: $filterElement::class);
            }

            $filterQueryBuilders[] = $filterQueryBuilder;
        }

        return $filterQueryBuilders;
    }

    private function createCallbackException(
        FilterException  $e,
        ConfiguredFilter $filter,
        mixed            $callback
    ): FilterException {
        if (!$errorMethod = $e->getMethod())
        {
            $serviceId = null;
            $method = '___UNKNOWN___';

            if (\is_object($callback))
            {
                $serviceId = $callback::class;
                $method = '::__invoke';
            }

            if (!$serviceId && \is_callable($callback))
            {
                try
                {
                    $reflection = new \ReflectionFunction($callback);
                    $serviceId = $reflection->getClosureScopeClass()?->getName() ?? 'Closure';
                    $method = '::' . $reflection->getName();
                }
                /** @mago-expect lint:no-empty-catch-clause ReflectionException is safely ignored here */
                catch (\ReflectionException) {}
            }

            if (!$serviceId)
            {
                $serviceId = \gettype($callback);
                $method = '()';
            }

            $errorMethod = $serviceId . $method;
        }

        return new FilterException(
            \sprintf('[FLARE] Query denied: %s / Callback: %s', $e->getMessage(), $errorMethod),
            code: $e->getCode(), previous: $e, method: $errorMethod,
            source: \sprintf('tl_flare_filter.id=%s', $filter->getDataSource()?->getFilterIdentifier() ?: '0'),
        );
    }
}