<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement;

use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class CodefogTagsSearchElement extends AbstractFilterElement implements DcaContract
{
    public const TYPE = 'cfg_tags_search';

    public function isSupported(): bool
    {
        return false;
    }

    public function configureDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},fieldGeneric,isMultiple,preselect');
    }
}
