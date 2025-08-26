<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;

#[AsFilterElement(
    alias: PublishedElement::TYPE,
    palette: '{filter_legend},usePublished,useStart,useStop'
)]
class PublishedElement extends AbstractFilterElement
{
    const TYPE = 'flare_published';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $filterModel = $context->getFilterModel();

        if ($filterModel->usePublished ?? true)
        {
            $publishedField = $qb->column($filterModel->fieldPublished ?: 'published');
            $invertPublished = $filterModel->invertPublished ?? false;
            $operator = $invertPublished ? '!=' : '=';

            // "published = 1" or "published != 1"
            $qb->where("$publishedField $operator :one", ['one' => 1]);
        }

        if ($filterModel->useStart ?? true)
        {
            $startField = $qb->column($filterModel->fieldStart ?: 'start');

            $qb->where("$startField = \"\" OR $startField = 0 OR $startField <= :start")
                ->setParameter('start', \time());
        }

        if ($filterModel->useStop ?? true)
        {
            $stopField = $qb->column($filterModel->fieldStop ?: 'stop');

            $qb->where("$stopField = \"\" OR $stopField = 0 OR $stopField >= :stop")
                ->setParameter('stop', \time());
        }
    }

    public static function define(
        string|false|null $published = null,
        string|false|null $start = null,
        string|false|null $stop = null,
        bool|null $invertPublished = null,
    ): FilterDefinition {
        $published ??= 'published';
        $start ??= 'start';
        $stop ??= 'stop';
        $invertPublished ??= false;

        $definition = new FilterDefinition(
            alias: static::TYPE,
            title: 'Is Published',
            intrinsic: true,
        );

        if ($published) {
            $definition->usePublished = true;
            $definition->fieldPublished = $published;
            $definition->invertPublished = $invertPublished;
        }

        if ($start) {
            $definition->useStart = true;
            $definition->fieldStart = $start;
        }

        if ($stop) {
            $definition->useStop = true;
            $definition->fieldStop = $stop;
        }

        return $definition;
    }
}