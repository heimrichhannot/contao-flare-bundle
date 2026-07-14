<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListType;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerBuilder;
use HeimrichHannot\FlareBundle\Contract;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractListType implements
    ListTypeInterface, OptionsContract, TransformerContract, Contract\ListType\BuildQueryContract
{
    /**
     * Declares the type's config schema on top of {@see \HeimrichHannot\FlareBundle\List\BaseListOptions}.
     */
    public function configureOptions(OptionsResolver $resolver): void {}

    public function configureTransformers(TransformerBuilder $transformers): void
    {
        $transformers->for(ListModel::class, $this->transformListModel(...));
    }

    /**
     * Translates a stored tl_flare_list model into the type's canonical config values (unresolved).
     * Base columns are already translated by {@see \HeimrichHannot\FlareBundle\List\BaseListOptions}.
     */
    protected function transformListModel(ListModel $model, ConfigBuilder $config): void {}

    public function buildTableRegistry(TableAliasRegistry $registry): void {}

    public function buildBaseQuery(SqlQueryStruct $struct): void {}
}
