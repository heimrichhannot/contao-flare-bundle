<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

#[AsFilterElement(alias: ArchiveElement::TYPE, formType: ChoiceType::class)]
class ArchiveElement extends BelongsToRelationElement implements FormTypeOptionsContract, PaletteContract
{
    public const TYPE = 'flare_archive';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $submittedWhitelist = $context->getSubmittedData();
        $filterModel = $context->getFilterModel();
        $inferrer = new PtableInferrer($filterModel, $context->getListModel());

        if ($inferrer->getDcaMainPtable())
        {
            if (!$whitelist = StringUtil::deserialize($filterModel->whitelistParents)) {
                throw new FilterException('No whitelisted parents defined.');
            }

            if (\is_array($submittedWhitelist))
            {
                $whitelist = \array_intersect($whitelist, $submittedWhitelist);

                if (empty($whitelist))
                {
                    $qb->blockList();
                    return;
                }
            }

            $qb->where("`pid` IN (:pidIn)")
                ->bind('pidIn', $whitelist);

            return;
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            $this->filterDynamicPtableField($qb, $filterModel, 'ptable', 'pid');
            return;
        }

        throw new FilterException('No valid ptable found.');
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

    /**
     * @throws FilterException
     */
    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        $filterModel = $context->getFilterModel();
        $inferrer = new PtableInferrer($filterModel, $context->getListModel());

        $choices->enable();
        $choices->setLabel('test');

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            if ($whitelist = StringUtil::deserialize($filterModel->whitelistParents))
            {
                $pClass = Model::getClassFromTable($ptable);

                if (!class_exists($pClass)) {
                    throw new FilterException('Invalid parent table class.');
                }

                $parents = $pClass::findMultipleByIds($whitelist);

                foreach ($parents as $parent)
                {
                    $choices->add($parent->id, $parent);
                }
            }

            return [
                'multiple' => true,
                'expanded' => true,
            ];
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            \dump('dynamic ptable');

            throw new FilterException('Not yet implemented.');
        }

        throw new FilterException('No valid ptable found.');
    }
}