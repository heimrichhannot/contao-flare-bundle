<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;

#[AsFilterElement(
    alias: PublishedElement::TYPE,
    palette: '{filter_legend},useStart,useStop'
)]
class PublishedElement extends AbstractFilterElement
{
    const TYPE = 'flare_published';

    public function __invoke(FilterQueryBuilder $qb, FilterContext $context): void
    {
        $qb->where("published = 1");

        $filterModel = $context->getFilterModel();

        if ($filterModel->useStart ?? true)
        {
            $startField = ($filterModel->field_start ?: "start");
            $qb->where("($startField = \"\" OR $startField = 0 OR $startField <= :start)")
                ->bind('start', time());
        }

        if ($filterModel->useStop ?? true)
        {
            $stopField = ($filterModel->field_stop ?: "stop");
            $qb->where("($stopField = \"\" OR $stopField = 0 OR $stopField >= :stop)")
                ->bind('stop', time());
        }
    }
}