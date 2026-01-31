<?php

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Generator\ReaderPageUrlGenerator;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<ValidationView>
 */
class ValidationProjector extends AbstractProjector
{
    public function __construct(
        private readonly Connection             $connection,
        private readonly ListQueryManager       $listQueryManager,
        private readonly ReaderPageUrlGenerator $readerPageUrlGenerator,
    ) {}

    public function supports(ContextInterface $config): bool
    {
        return $config instanceof ValidationContext;
    }

    public function project(ListSpecification $spec, ContextInterface $config): ValidationView
    {
        \assert($config instanceof ValidationContext, '$config must be an instance of ValidationConfig');

        $autoItemField = $config->getAutoItemField();
        $readerUrlGenerator = $this->readerPageUrlGenerator->createCallable($config);

        return new ValidationView(
            fetchEntryById: function (int $id) use ($spec, $config): ?array {
                return $this->fetchEntry($id, $spec, $config);
            },
            fetchEntryByAutoItem: function (string $autoItem) use ($autoItemField, $spec, $config): ?array {
                return $this->fetchEntryByAutoItem($autoItem, $autoItemField, $spec, $config);
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

            $spec->filters->add($idDefinition);

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

            $spec->filters->add($autoItemDefinition);

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
        $listQueryBuilder = $this->listQueryManager->prepare($spec);

        $filterValues = $this->gatherFilterValues($spec, $config->getFilterValues());
        $this->listQueryManager->populate($listQueryBuilder, $spec, $config, $filterValues);

        $query = $this->listQueryManager->populate(
            listQueryBuilder: $listQueryBuilder,
            listSpecification: $spec,
            contextConfig: $config,
        );

        if (!$query->isAllowed())
        {
            return [];
        }

        $result = $query->execute($this->connection);

        $entry = $result->fetchAssociative();

        $result->free();

        return $entry ?: null;
    }
}