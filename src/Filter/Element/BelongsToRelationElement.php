<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Message;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;

#[AsFilterElement(alias: BelongsToRelationElement::TYPE)]
class BelongsToRelationElement extends AbstractFilterElement implements PaletteContract
{
    const TYPE = 'flare_relation_belongsTo';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $filterModel = $context->getFilterModel();
        $listModel = $context->getListModel();

        if (!$fieldPid = $filterModel->fieldPid)
        {
            throw new FilterException('No parent field defined.');
        }

        $inferrer = new PtableInferrer($filterModel, $listModel);

        try
        {
            $ptable = $inferrer->explicit();
            $fieldDynamicPtable = $inferrer->tryGetDynamicPtableField();
        }
        catch (InferenceException)
        {
            $qb->blockList();
            return;
        }

        if (\is_string($fieldDynamicPtable))
        {
            $this->filterDynamicPtableField($qb, $filterModel, $fieldDynamicPtable, $fieldPid);
            return;
        }

        if (!$ptable || !$whitelistParents = StringUtil::deserialize($filterModel->whitelistParents)) {
            throw new FilterException('No whitelisted parents.');
        }

        $qb->where($qb->expr()->in($fieldPid, ":whitelist"))
            ->bind('whitelist', $whitelistParents);
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
    public function filterDynamicPtableField(
        FilterQueryBuilder $qb,
        FilterModel        $filterModel,
        string             $fieldDynamicPtable,
        string             $fieldPid,
        ?array             $submittedData = null,
    ): void {
        if (!$parentGroups = StringUtil::deserialize($filterModel->groupWhitelistParents))
        {
            $qb->blockList();
            return;
        }

        $ors = [];

        foreach (\array_values($parentGroups) as $i => $group)
        {
            if (!($g_tablePtable = $group['tablePtable'] ?? null)
                || !($g_whitelistParents = $group['whitelistParents'] ?? null)
                || !\is_array($g_whitelistParents = StringUtil::deserialize($g_whitelistParents)))
            {
                continue;
            }

            if (isset($submittedData))
            {
                $submittedWhitelist = $submittedData[$g_tablePtable] ?? null;

                if (!\is_array($submittedWhitelist)) {
                    continue;
                }

                $g_whitelistParents = \array_intersect($g_whitelistParents, $submittedWhitelist);
            }

            if (empty($g_whitelistParents)) {
                continue;
            }

            $gKey_tablePtable = \sprintf(':g%s_ptable', $i);
            $gKey_whitelistParents = \sprintf(':g%s_whitelist', $i);

            $ors[] = $qb->expr()->and(
                $qb->expr()->eq($fieldDynamicPtable, $gKey_tablePtable),
                $qb->expr()->in($fieldPid, $gKey_whitelistParents)
            );

            $qb->bind($gKey_tablePtable, $g_tablePtable);
            $qb->bind($gKey_whitelistParents, $g_whitelistParents);
        }

        if (empty($ors))
        {
            $qb->blockList();
            return;
        }

        if (\count($ors) === 1)
        {
            $qb->where($ors[0]);
            return;
        }

        $qb->where($qb->expr()->or(...$ors));
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $listModel = $config->getListModel();
        $filterModel = $config->getFilterModel();

        if (!$listModel || !$filterModel) {
            Message::addError('List model or filter model not found.');
            return '';
        }

        if (!$listModel->dc) {
            Message::addError('Please define a data container on the list model ' . $listModel->getTable());
            return '';
        }

        $palette = '{filter_legend},fieldPid,whichPtable';

        $inferrer = new PtableInferrer($filterModel, $listModel);
        $table = $inferrer->getEntityTable();
        $fieldPid = $inferrer->getPidField();

        try
        {
            $ptable = $inferrer->explicit(true);

            Message::addInfo(match (true) {
                $inferrer->isAutoInferable() && $ptable => \sprintf('Parent table of "%s.%s" inferred as "%s"', $table, $fieldPid, $ptable),
                $inferrer->isAutoDynamicPtable() => \sprintf('Parent table of "%s" can be inferred dynamically', $table),
                default => \sprintf('Parent table cannot be inferred on "%s.%s"', $table, $fieldPid)
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
        elseif ($ptable ?? null)
        {
            $palette .= ',whitelistParents';
        }

        return $palette;
    }
}