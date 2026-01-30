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

        $readerUrlGenerator = $this->readerPageUrlGenerator->createCallable($config);

        return new ValidationView(
            fetchEntry: function (int $id) use ($spec, $config): ?array {
                return $this->fetchEntry($id, $spec, $config);
            },
            readerUrlGenerator: $readerUrlGenerator,
            table: $spec->dc,
        );
    }

    public function fetchEntry(int $id, ListSpecification $spec, ValidationContext $config): ?array
    {
        // Fast lane cache check
        if ($hit = $config->getEntryCache()[$id] ?? null)
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
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e);
        }
    }
}