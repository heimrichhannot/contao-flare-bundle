<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Type;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\ListType\BuildQueryContract;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\List\CallbackListModelTransformer;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractListDriver implements
    ListDriverInterface, OptionsContract, TransformerContract, BuildQueryContract
{
    /**
     * Declares the type's config schema on top of {@see \HeimrichHannot\FlareBundle\List\BaseListOptions}.
     */
    public function configureOptions(OptionsResolver $resolver): void {}

    public function configureTransformers(TransformerResolver $resolver): void
    {
        $resolver->for(
            sourceClass: ListModel::class,
            transformer: new CallbackListModelTransformer($this->transformListModel(...)),
        );
    }

    /**
     * Translates a stored tl_flare_list model into the type's canonical config values (unresolved).
     * Base columns are already translated by {@see \HeimrichHannot\FlareBundle\List\BaseListOptions}.
     */
    protected function transformListModel(ConfigBuilder $config, ListModel $model): void {}

    public function buildTableRegistry(TableAliasRegistry $registry): void {}

    public function buildBaseQuery(SqlQueryStruct $struct): void {}
}
