<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Collector;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('flare.filter_collector')]
interface FilterCollectorInterface
{
    public function supports(ListDataSourceInterface $dataSource): bool;

    /**
     * @return array<string, Filter>|null Filters keyed by their list-specification key.
     */
    public function collect(ListDataSourceInterface $dataSource): ?array;
}
