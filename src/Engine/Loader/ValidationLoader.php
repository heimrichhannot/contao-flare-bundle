<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterCompoundFactory;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;

readonly class ValidationLoader implements ValidationLoaderInterface
{
    public function __construct(
        private ValidationLoaderConfig $config,
        private FilterCompoundFactory  $filterCompoundFactory,
        private ListQueryDirector      $listQueryDirector,
    ) {}

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
            $autoItemDefinition = $this->filterCompoundFactory->create(
                element: SimpleEquationFilterElement::TYPE,
                alias: '_.autoItem',
                config: [
                    'left' => $this->config->autoItemField,
                    'operator' => SqlEquationOperator::EQUALS,
                    'right' => $autoItem,
                ],
            );

            $qb = $this->createQueryBuilder($this->config->list->withFilter($autoItemDefinition));

            if (!$qb) {
                return null;
            }

            $result = $qb->executeQuery();
            $row = $result->fetchAssociative();
            $result->free();

            return $row === false ? null : $row;
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Exception $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e, method: __METHOD__);
        }
    }

    /**
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     *
     * @throws FlareException
     */
    public function fetchEntriesByIds(array $ids): array
    {
        try
        {
            $qb = $this->createQueryBuilder($this->config->list);

            if (!$qb) {
                return [];
            }

            $qb->andWhere($qb->expr()->in(TableAliasRegistry::ALIAS_MAIN . '.id', ':flare_lookup_ids'))
                ->setParameter('flare_lookup_ids', $ids, ArrayParameterType::INTEGER)
                ->setFirstResult(0)
                ->setMaxResults(null);

            return $qb->executeQuery()->fetchAllAssociative();
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Exception $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e, method: __METHOD__);
        }
    }

    /**
     * The query of $list by the reader's rules: the intent's filter values, no form values; null if a filter aborts.
     *
     * @throws FlareException|FilterException
     */
    private function createQueryBuilder(ListCompound $list): ?QueryBuilder
    {
        return $this->listQueryDirector->createQueryBuilder(new ListQueryConfig(
            list: $list,
            intent: $this->config->intent,
            filterValues: $this->config->intent->getFilterValues(),
        ));
    }
}
