<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\Message;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\Type\BelongsToRelationFilterType;
use HeimrichHannot\FlareBundle\InferPtable\Factory\PtableInferrableFactory;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFilterElement(type: self::TYPE)]
class BelongsToRelationElement extends AbstractFilterElement
{
    public const TYPE = 'flare_relation_belongsTo';

    public function __construct(
        private readonly TranslatorInterface $trans,
    ) {}

    /**
     * @throws FilterException
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterInvocation $invocation): void
    {
        $filter = $invocation->filter;

        if (!$fieldPid = $filter->fieldPid)
        {
            throw new FilterException('No parent field defined.');
        }

        $inferrable = PtableInferrableFactory::createFromListModelLike($invocation->list);
        $inferrer = new PtableInferrer($inferrable, $invocation->list->dc);

        try
        {
            $ptable = $inferrer->getInferredPtable();
            $fieldDynamicPtable = $inferrer->tryGetDynamicPtableField();
        }
        catch (InferenceException)
        {
            $builder->abort();
        }

        if (\is_string($fieldDynamicPtable))
        {
            $builder->add(BelongsToRelationFilterType::class, [
                'field_pid' => $fieldPid,
                'field_dynamic_ptable' => $fieldDynamicPtable,
                'parent_groups' => $this->getDynamicParentGroups($filter),
            ]);

            return;
        }

        if (!$ptable || !$whitelistParents = StringUtil::deserialize($filter->whitelistParents)) {
            throw new FilterException('No whitelisted parents.');
        }

        $builder->add(BelongsToRelationFilterType::class, [
            'field_pid' => $fieldPid,
            'whitelist' => (array) $whitelistParents,
        ]);
    }

    /**
     * Expected format:
     * ```php
     *   $submittedData = [
     *       'tl_article' => [1, 5, 35, ...],
     *       'tl_news' => [2, 3, 4, ...],
     *   ];
     * ```
     */
    public function addDynamicPtableFilter(
        FilterBuilderInterface $builder,
        ConfiguredFilter       $filter,
        string                 $fieldDynamicPtable,
        string                 $fieldPid,
        ?array                 $submittedData = null,
    ): void {
        $builder->add(BelongsToRelationFilterType::class, [
            'field_pid' => $fieldPid,
            'field_dynamic_ptable' => $fieldDynamicPtable,
            'parent_groups' => $this->getDynamicParentGroups($filter),
            'submitted_data' => $submittedData,
        ]);
    }

    public function getDynamicParentGroups(ConfiguredFilter $filter): array
    {
        if (!$parentGroups = StringUtil::deserialize($filter->groupWhitelistParents))
        {
            return [];
        }

        $groups = [];

        foreach (\array_values($parentGroups) as $group)
        {
            if (!($g_tablePtable = $group['tablePtable'] ?? null)
                || !($g_whitelistParents = $group['whitelistParents'] ?? null)
                || !\is_array($g_whitelistParents = StringUtil::deserialize($g_whitelistParents)))
            {
                continue;
            }

            $g_whitelistParents = \array_values(\array_filter($g_whitelistParents));

            if (!$g_whitelistParents) {
                continue;
            }

            $groups[] = [
                'table' => $g_tablePtable,
                'ids' => $g_whitelistParents,
            ];
        }

        return $groups;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $listModel = $config->getListModel();
        $filterModel = $config->getFilterModel();

        if (!$filterModel) {
            Message::addError($this->trans->trans('errors.missing_model', [], 'flare'));
            return '';
        }

        if (!$listModel->dc) {
            Message::addError($this->trans->trans('errors.missing_datacontainer', [
                '%id%' => $listModel->id,
            ], 'flare'));
            return '';
        }

        $palette = '{filter_legend},fieldPid,whichPtable';

        $inferrer = new PtableInferrer($filterModel, $listModel->dc);
        $table = $inferrer->getEntityTable();
        $fieldPid = $inferrer->getPidField();

        try
        {
            $ptable = $inferrer->getInferredPtable();

            Message::addInfo(match (true) {
                $inferrer->isAutoInferable() && $ptable => $this->trans->trans('infer_ptable.auto', [
                    '%table%' => $table,
                    '%field%' => $fieldPid,
                    '%ptable%' => $ptable,
                ], 'flare'),
                $inferrer->isAutoDynamicPtable() => $this->trans->trans('infer_ptable.dynamic', [
                    '%table%' => $table,
                ], 'flare'),
                default => $this->trans->trans('infer_ptable.invalid', [
                    '%table%' => $table,
                    '%field%' => $fieldPid,
                ], 'flare')
            });
        }
        catch (InferenceException $e)
        {
            Message::addError($e->getMessage());
        }

        if (!$inferrer->isAutoInferable())
        {
            $filterModel->whichPtable_disableAutoOption();
        }

        if ($filterModel->whichPtable === 'dynamic')
        {
            $palette .= ';{archive_legend},groupWhitelistParents';
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        elseif ($ptable ?? null)
        {
            $palette .= ',whitelistParents';
        }

        return $palette;
    }
}
