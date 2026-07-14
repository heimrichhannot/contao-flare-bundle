<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Lists\ListSpec;

readonly class ValidationLoader implements ValidationLoaderInterface
{
    public function __construct(
        private ValidationLoaderConfig $config,
        private ListQueryDirector      $listQueryDirector,
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
            $idDefinition = new Filter(
                element: SimpleEquationFilterElement::TYPE,
                config: [
                    'intrinsic' => true,
                    'left' => 'id',
                    'operator' => SqlEquationOperator::EQUALS,
                    'right' => $id,
                ],
            );

            $list = $this->config->list->withFilter($idDefinition);

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
            $autoItemDefinition = new Filter(
                element: SimpleEquationFilterElement::TYPE,
                config: [
                    'intrinsic' => true,
                    'left' => $this->config->autoItemField,
                    'operator' => SqlEquationOperator::EQUALS,
                    'right' => $autoItem,
                ],
            );

            $list = $this->config->list->withFilter($autoItemDefinition);

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
    private function executeQuery(ListSpec $list, ValidationContext $context): ?array
    {
        $qb = $this->listQueryDirector->createQueryBuilder(new ListQueryConfig(
            list: $list,
            context: $context,
            filterValues: $context->getFilterValues(),
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
