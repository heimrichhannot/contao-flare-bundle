<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterFilterElement;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class CodefogTagsSearchElement extends AbstractFilterFilterElement
{
    public const TYPE = 'cfg_tags_search';

    public function isSupported(): bool
    {
        return false;
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},fieldGeneric,isMultiple,preselect');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // TODO: Implement configureOptions() method.
    }

    public function configFromRow(array $row): array
    {
        // TODO: Implement configFromRow() method.
        return [];
    }
}
