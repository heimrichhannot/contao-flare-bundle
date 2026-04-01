<?php

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterValueResolver;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class ValidationLoader implements ValidationLoaderInterface
{
    public function __construct(
        private FilterValueResolver    $filterValueResolver,
        private ListQueryDirector      $listQueryDirector,
        private ValidationLoaderConfig $config,
    ) {}

    /**
     * @throws FlareException
     */
    public function fetchEntryById(int $id): ?array
    {
        if ($hit = $this->config->context->getEntryCache()[$id] ?? null)
            // Fast lane cache check
        {
            return $hit;
        }

        try
        {
            // IMPORTANT: clone the spec to not modify the original, i.e., when adding the id filter
            $list = clone $this->config->list;

            $idDefinition = SimpleEquationElement::define(
                equationLeft: 'id',
                equationOperator: SqlEquationOperator::EQUALS,
                equationRight: $id,
            );

            $list->getFilters()->add($idDefinition);

            return $this->executeQuery($list, $this->config->context);
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws FlareException
     */
    public function fetchEntryByAutoItem(string $autoItem): ?array
    {
        if (!$this->config->autoItemField || !$autoItem) {
            return null;
        }

        try
        {
            // IMPORTANT: clone the spec to not modify the original
            $list = clone $this->config->list;

            $autoItemDefinition = SimpleEquationElement::define(
                equationLeft: $this->config->autoItemField,
                equationOperator: SqlEquationOperator::EQUALS,
                equationRight: $autoItem,
            );

            $list->getFilters()->add($autoItemDefinition);

            return $this->executeQuery($list, $this->config->context);
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws \Exception
     */
    private function executeQuery(ListSpecification $spec, ValidationContext $config): ?array
    {
        $qb = $this->listQueryDirector->createQueryBuilder(new ListQueryConfig(
            list: $spec,
            context: $config,
            filterValues: $this->filterValueResolver->resolve($spec, $config->getFilterValues()),
        ));

        if (!$qb) {
            return [];
        }

        $result = $qb->executeQuery();

        $entry = $result->fetchAssociative();

        $result->free();

        return $entry ?: null;
    }
}