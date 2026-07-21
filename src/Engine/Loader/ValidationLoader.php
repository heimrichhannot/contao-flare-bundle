<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Util\EntryCache;

readonly class ValidationLoader implements ValidationLoaderInterface
{
    protected EntryCache $entryCache;

    public function __construct(
        private ValidationLoaderConfig $config,
        private FilterFactory          $filterFactory,
        private ListQueryDirector      $listQueryDirector,
    ) {
        $this->entryCache = new EntryCache($this->config->list->dc);
    }

    public function getEntryCache(): EntryCache
    {
        return $this->entryCache;
    }

    /**
     * @throws FlareException
     */
    public function fetchEntryById(int $id): ?array
    {
        if ($this->entryCache->has($id))
        {
            return $this->entryCache->get($id);
        }

        try
        {
            $idDefinition = $this->filterFactory->create(
                element: SimpleEquationFilterElement::TYPE,
                config: [
                    'intrinsic' => true,
                    'left' => 'id',
                    'operator' => SqlEquationOperator::EQUALS,
                    'right' => $id,
                ],
            );

            $list = $this->config->list->withFilter($idDefinition);

            $entry = $this->executeQuery($list, $this->config->context);

            $this->entryCache->add('id:' . $id, $entry);

            if (($autoItemField = $this->config->autoItemField)
                && ($autoItem = $entry[$autoItemField] ?? null))
            {
                $this->entryCache->add('autoItem:' . $autoItem, $entry);
            }

            return $entry;
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e, method: __METHOD__);
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

        if ($entry = $this->entryCache->get('autoItem:' . $autoItem)) {
            return $entry;
        }

        try
        {
            $autoItemDefinition = $this->filterFactory->create(
                element: SimpleEquationFilterElement::TYPE,
                config: [
                    'intrinsic' => true,
                    'left' => $this->config->autoItemField,
                    'operator' => SqlEquationOperator::EQUALS,
                    'right' => $autoItem,
                ],
            );

            $list = $this->config->list->withFilter($autoItemDefinition);

            $entry = $this->executeQuery($list, $this->config->context);

            $this->entryCache->add('autoItem:' . $autoItem, $entry);

            if ($id = $entry['id'] ?? null) {
                $this->entryCache->add('id:' . $id, $entry);
            }

            return $entry;
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e, method: __METHOD__);
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
