<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterCallback;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsChoiceElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsSearchElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry\CfgTagsJoinsRegistry;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;

readonly class TargetAliasCallback
{
    public function __construct(
        private CfgTagsJoinsRegistry $joinsRegistry,
    ) {}

    #[AsFilterCallback(CodefogTagsChoiceElement::TYPE, 'fields.targetAlias.options', priority: 20)]
    #[AsFilterCallback(CodefogTagsSearchElement::TYPE, 'fields.targetAlias.options', priority: 20)]
    public function onTargetAliasOptions(ListExecutionContext $context): ?array
    {
        $activeTagsAliases = \array_intersect_key(
            $this->joinsRegistry->all(),
            \array_flip($context->tableAliasRegistry->getAliases()),
        );

        if (!$activeTagsAliases) {
            return null;
        }

        $options = [];

        foreach ($activeTagsAliases as $alias => $config) {
            $options[$alias] = "{$alias} [tl_cfg_tag]";
        }

        return $options;
    }
}
