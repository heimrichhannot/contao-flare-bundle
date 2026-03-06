<?php

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterCallback;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsChoiceElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsSearchElement;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;

readonly class TargetAliasCallback
{
    #[AsFilterCallback(CodefogTagsChoiceElement::TYPE, 'fields.targetAlias.options', priority: 20)]
    #[AsFilterCallback(CodefogTagsSearchElement::TYPE, 'fields.targetAlias.options', priority: 20)]
    public function onTargetAliasOptions(ListExecutionContext $context): ?array
    {
        $tables = $context->tableAliasRegistry->getTablesWithAttribute('codefog_tags_field');
        if (!$tables) {
            return null;
        }

        $options = [];

        foreach ($tables as $alias => $table) {
            $options[] = "{$alias} [{$table}]";
        }

        return $options;
    }
}