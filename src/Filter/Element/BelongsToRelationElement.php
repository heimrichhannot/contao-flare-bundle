<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Message;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;

#[AsFilterElement(alias: BelongsToRelationElement::TYPE)]
class BelongsToRelationElement extends AbstractFilterElement implements PaletteContract
{
    const TYPE = 'flare_relation_belongsTo';

    public function __invoke(FilterQueryBuilder $qb, FilterContext $context): void
    {
        $filterModel = $context->getFilterModel();
        $listModel = $context->getListModel();

        if (!$fieldPid = $filterModel->fieldPid)
        {
            $qb->where('1 = 0');
            return;
        }

        $inferrer = new PtableInferrer($filterModel, $listModel);

        try
        {
            $ptable = $inferrer->explicit();
            $fieldDynamicPtable = $inferrer->tryGetDynamicPtableField();
        }
        catch (InferenceException)
        {
            $qb->where('1 = 0');
            return;
        }

        if (\is_string($fieldDynamicPtable))
        {
            $this->filterDynamicPtableField($qb, $filterModel, $fieldDynamicPtable, $fieldPid);
            return;
        }

        if (!$ptable || !$whitelistParents = StringUtil::deserialize($filterModel->whitelistParents)) {
            $qb->where('1 = 0');
            return;
        }

        $qb->where($qb->expr()->in($fieldPid, ":whitelist"))
            ->bind('whitelist', $whitelistParents);
    }

    public function filterDynamicPtableField(
        FilterQueryBuilder $qb,
        FilterModel        $filterModel,
        string             $fieldDynamicPtable,
        string             $fieldPid
    ): void {
        if (!$parentGroups = StringUtil::deserialize($filterModel->groupWhitelistParents))
        {
            $qb->where('1 = 0');
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
            $qb->where('1 = 0');
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

        try
        {
            $ptable = $inferrer->explicit(true);

            Message::addInfo(match (true) {
                $inferrer->isAutoInferable() => \sprintf('Parent table of "%s.%s" inferred as "%s"', $listModel->dc, $filterModel->fieldPid, $ptable),
                $inferrer->isAutoDynamicPtable() => \sprintf('Parent table of "%s" can be inferred dynamically', $listModel->dc),
                default => \sprintf('Parent table cannot be inferred on "%s.%s"', $listModel->dc, $filterModel->fieldPid)
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