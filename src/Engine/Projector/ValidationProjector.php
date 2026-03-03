<?php

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Generator\ReaderPageUrlGenerator;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<ValidationView>
 */
class ValidationProjector extends AbstractProjector
{
    public function __construct(
        private readonly ReaderPageUrlGenerator $readerPageUrlGenerator,
    ) {}

    public function supports(ListSpecification $spec, ContextInterface $context): bool
    {
        return $context instanceof ValidationContext;
    }

    public function project(ListSpecification $spec, ContextInterface $context): ValidationView
    {
        \assert($context instanceof ValidationContext, '$config must be an instance of ValidationConfig');

        $autoItemField = $context->getAutoItemField();
        $readerUrlGenerator = $this->readerPageUrlGenerator->createCallable($context);

        return new ValidationView(
            fetchEntryById: function (int $id) use ($spec, $context): ?array {
                return $this->fetchEntry($id, $spec, $context);
            },
            fetchEntryByAutoItem: function (string $autoItem) use ($autoItemField, $spec, $context): ?array {
                return $this->fetchEntryByAutoItem($autoItem, $autoItemField, $spec, $context);
            },
            readerUrlGenerator: $readerUrlGenerator,
            table: $spec->dc,
            autoItemField: $autoItemField,
        );
    }

    /**
     * @throws FlareException
     */
    public function fetchEntry(int $id, ListSpecification $spec, ValidationContext $config): ?array
    {
        if ($hit = $config->getEntryCache()[$id] ?? null)
            // Fast lane cache check
        {
            return $hit;
        }

        try
        {
            // IMPORTANT: clone the spec to not modify the original, i.e., when adding the id filter
            $spec = clone $spec;

            $idDefinition = SimpleEquationElement::define(
                equationLeft: 'id',
                equationOperator: SqlEquationOperator::EQUALS,
                equationRight: $id,
            );

            $spec->getFilters()->add($idDefinition);

            return $this->executeQuery($spec, $config);
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
    public function fetchEntryByAutoItem(
        string            $autoItem,
        string            $autoItemField,
        ListSpecification $spec,
        ValidationContext $config
    ): ?array {
        if (!$autoItemField || !$autoItem) {
            return null;
        }

        try
        {
            // IMPORTANT: clone the spec to not modify the original
            $spec = clone $spec;

            $autoItemDefinition = SimpleEquationElement::define(
                equationLeft: $autoItemField,
                equationOperator: SqlEquationOperator::EQUALS,
                equationRight: $autoItem,
            );

            $spec->getFilters()->add($autoItemDefinition);

            return $this->executeQuery($spec, $config);
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
        $qb = $this->createQueryBuilder(new ListQueryConfig(
            list: $spec,
            context: $config,
            filterValues: $this->gatherFilterValues($spec, $config->getFilterValues()),
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