<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterCollector;

use HeimrichHannot\FlareBundle\Collection\ConfiguredFilterCollection;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('flare.filter_collector')]
interface FilterCollectorInterface
{
    public function supports(ListDataSourceInterface $dataSource): bool;

    public function collect(ListDataSourceInterface $dataSource): ?ConfiguredFilterCollection;
}