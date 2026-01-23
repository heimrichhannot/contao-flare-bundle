<?php

namespace HeimrichHannot\FlareBundle\Filter\Collector;

use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\List\ListDataSource;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('flare.filter_collector')]
interface FilterCollectorInterface
{
    public function supports(ListDataSource $dataSource): bool;

    public function collect(ListDataSource $dataSource): ?FilterDefinitionCollection;
}