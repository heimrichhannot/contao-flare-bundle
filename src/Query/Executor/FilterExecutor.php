<?php

namespace HeimrichHannot\FlareBundle\Query\Executor;

use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterExecutor
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private FilterElementRegistry     $filterElementRegistry,
        private FilterInvokerResolver     $filterInvoker,
        private FilterQueryBuilderFactory $filterQueryBuilderFactory,
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
         * @var FilterDefinition $filter
         */
        foreach ($list->getFilters()->all() as $key => $filter)
        {
            $invocation = new FilterInvocation(
                filter: $filter,
                list: $list,
                context: $context,
                value: $options->filterValues[$key] ?? null,
            );

            if (!$filterQueryBuilder = $this->invokeFilter($invocation)) {
                continue;
            }

            $filterQueryBuilders[] = $filterQueryBuilder;
        }

        return $filterQueryBuilders;
    }

    /**
     * @throws AbortFilteringException
     * @throws FilterException
     * @throws FlareException
     */
    public function invokeFilter(FilterInvocation $invocation): ?FilterQueryBuilder
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

        if (!$filterElementDescriptor = $this->filterElementRegistry->get($filter->getType())) {
            return null;
        }

        if (!$callback = $this->filterInvoker->get(
            filterType: $filter->getType(),
            contextType: $context::getContextType()
        )) {
            return null;
        }

        $event = $this->eventDispatcher->dispatch(new FilterElementInvokingEvent(
            invocation: $invocation,
            context: $context,
            callback: $callback,
            shouldInvoke: true,
        ));

        if (!$event->shouldInvoke()) {
            return null;
        }

        $callback = $event->getCallback();

        $targetAlias = TableAliasRegistry::ALIAS_MAIN;
        if ($filterElementDescriptor->isTargeted() || $filter->isTargetingForced()) {
            $targetAlias = $filter->getTargetAlias() ?: TableAliasRegistry::ALIAS_MAIN;
        }

        $filterQueryBuilder = $this->filterQueryBuilderFactory->create($targetAlias);

        try
        {
            $callback($invocation, $filterQueryBuilder);
        }
        catch (AbortFilteringException $e)
        {
            throw $e;
        }
        catch (FilterException $e)
        {
            throw $this->createCallbackException($e, $filter, $callback);
        }
        catch (\Throwable $e)
        {
            throw new FilterException($e->getMessage(), code: $e->getCode(), previous: $e, method: __METHOD__);
        }

        $this->eventDispatcher->dispatch(new FilterElementInvokedEvent($invocation, $filterQueryBuilder));

        return $filterQueryBuilder;
    }

    private function createCallbackException(
        FilterException  $e,
        FilterDefinition $filter,
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
            elseif (\is_callable($callback))
            {
                try
                {
                    $reflection = new \ReflectionFunction($callback);
                    $serviceId = $reflection->getClosureScopeClass()?->getName() ?? 'Closure';
                    $method = '::' . $reflection->getName();
                }
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
            source: \sprintf('tl_flare_filter.id=%s', $filter->getSourceFilterModel()?->id ?: '0'),
        );
    }
}