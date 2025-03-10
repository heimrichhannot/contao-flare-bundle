<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;

#[AsFilterElement(alias: ArchiveElement::TYPE)]
class ArchiveElement extends AbstractFilterElement implements PaletteContract
{
    public const TYPE = 'flare_archive';

    public function __invoke(FilterQueryBuilder $qb, FilterContext $context): void
    {
        $filterModel = $context->getFilterModel();
        $inferrer = new PtableInferrer($filterModel, $context->getListModel());

        if ($inferrer->getDcaMainPtable())
        {
            if (!$whitelist = StringUtil::deserialize($filterModel->whitelistParents)) {
                $qb->where('1 = 0');
                return;
            }

            $qb->where("`pid` IN (:pidIn)")
                ->bind('pidIn', \implode(', ', $whitelist));

            return;
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            // todo
            return;
        }

        $qb->where('1 = 0');
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $filterModel = $config->getFilterModel();
        $inferrer = new PtableInferrer($filterModel, $config->getListModel());

        if ($inferrer->getDcaMainPtable())
        {
            return '{archive_legend},whitelistParents';
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            return '{archive_legend},groupWhitelistParents';
        }

        return null;
    }

    #[AsFilterCallback(self::TYPE, 'fields.whitelistParents.options')]
    public function getOptions_whitelistParents(FilterModel $filterModel, ListModel $listModel): array
    {
        $inferrer = new PtableInferrer($filterModel, $listModel);

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            return DcaHelper::getArchiveOptions($ptable);
        }

        return [];
    }
}