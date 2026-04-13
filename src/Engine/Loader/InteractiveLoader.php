<?php

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;

readonly class InteractiveLoader implements InteractiveLoaderInterface
{
    public function __construct(
        private InteractiveLoaderConfig $config,
        private ListQueryDirector       $listQueryDirector,
    ) {}

    /**
     * @throws FlareException|FilterException
     */
    public function fetchEntries(): array
    {
        try
        {
            $queryConfig = new ListQueryConfig(
                list: $this->config->list,
                context: $this->config->context,
                filterValues: $this->config->filterValues,
            );

            $qb = $this->listQueryDirector->createQueryBuilder($queryConfig);

            if (!$qb) {
                return [];
            }

            $result = $qb->executeQuery();

            $entries = $result->fetchAllAssociative();

            $result->free();

            return $entries;
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
}