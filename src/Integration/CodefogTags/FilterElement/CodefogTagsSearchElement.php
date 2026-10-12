<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class CodefogTagsSearchElement extends AbstractFilterElement
{
    public const TYPE = 'cfg_tags_search';

    public function isSupported(): bool
    {
        return false;
    }

    public function buildDca(DcaBuilderInterface $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},fieldGeneric,isMultiple,preselect');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // TODO: Implement configureOptions() method.
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        // TODO: Implement transformFilterModel() method.
    }
}
