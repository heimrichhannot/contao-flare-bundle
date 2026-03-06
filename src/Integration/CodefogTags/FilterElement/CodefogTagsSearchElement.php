<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use Symfony\Component\Form\Extension\Core\Type\SearchType;

#[AsFilterElement(
    type: self::TYPE,
    palette: '{filter_legend},fieldGeneric,isMultiple,preselect',
    formType: SearchType::class,
    isTargeted: true,
)]
class CodefogTagsSearchElement extends AbstractFilterElement
{
    public const TYPE = 'cfg_tags_search';

    #[AsFilterCallback(self::TYPE, 'fields.targetAlias.options', priority: 20)]
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