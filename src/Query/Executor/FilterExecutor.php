<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query\Executor;

use HeimrichHannot\FlareBundle\Event\FilterElementBuildingEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementBuiltEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterContextFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterCall;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterTypeRegistry;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterExecutor
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private FilterContextFactory      $filterContextFactory,
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

        $filterQueryBuilders = [];

        foreach ($list->filters as $key => $filter)
        {
            $context = $this->filterContextFactory->create($list, $filter, $filter->element, $options->context, $key);

            $data = (array) ($options->filterValues[$key] ?? $filter->data ?? []);

            if (!$builders = $this->invokeFilter($filter, $context, $data)) {
                continue;
            }

            \array_push($filterQueryBuilders, ...$builders);
        }

        return $filterQueryBuilders;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return FilterQueryBuilder[]
     *
     * @throws AbortFilteringException
     * @throws FilterException
     * @throws FlareException
     */
    public function invokeFilter(Filter $filter, FilterContext $context, array $data = []): array
    {
        if (!Str::isValidSqlName($table = $context->list->dc))
        {
            throw new FlareException(\sprintf(
                '[FLARE] ListSpec data container cannot be used as SQL table identifier: "%s"',
                $table
            ), method: __METHOD__);
        }

        $isTargeted = $this->filterElementRegistry->getAttribute($filter->type)?->isTargeted;

        $targetAlias = TableAliasRegistry::ALIAS_MAIN;
        if ($isTargeted || $filter->targetingForced) {
            $targetAlias = $filter->targetAlias ?: TableAliasRegistry::ALIAS_MAIN;
        }

        $builder = new FilterBuilder($this->filterTypeRegistry, $targetAlias);

        $event = $this->eventDispatcher->dispatch(new FilterElementBuildingEvent(
            context: $context,
            builder: $builder,
            data: $data,
        ));

        if (!$event->shouldBuild) {
            return [];
        }

        try
        {
            $filter->element->buildFilter($builder, $context, $data);
        }
        catch (AbortFilteringException $e)
        {
            throw $e;
        }
        catch (FilterException $e)
        {
            throw $this->createFilterException($e, $filter, $filter->element::class . '::buildFilter');
        }
        catch (\Throwable $e)
        {
            throw new FilterException($e->getMessage(), code: $e->getCode(), previous: $e, method: __METHOD__);
        }

        $this->eventDispatcher->dispatch(new FilterElementBuiltEvent($context, $builder, $data));

        return $this->buildQueryBuilders($builder->all(), $filter);
    }

    /**
     * @param FilterCall[] $calls
     * @return FilterQueryBuilder[]
     */
    private function buildQueryBuilders(array $calls, Filter $filter): array
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
                throw $this->createFilterException($e, $filter, $call->typeClass . '::buildQuery');
            }
            catch (\Throwable $e)
            {
                throw new FilterException($e->getMessage(), code: $e->getCode(), previous: $e, method: $call->typeClass);
            }

            $filterQueryBuilders[] = $filterQueryBuilder;
        }

        return $filterQueryBuilders;
    }

    private function createFilterException(FilterException $e, Filter $filter, string $fallbackMethod): FilterException
    {
        $errorMethod = $e->getMethod() ?: $fallbackMethod;

        return new FilterException(
            \sprintf('[FLARE] Query denied: %s / Callback: %s', $e->getMessage(), $errorMethod),
            code: $e->getCode(), previous: $e, method: $errorMethod,
            source: $filter->source ?: 'filter inlined',
        );
    }
}
