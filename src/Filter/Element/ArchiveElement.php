<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\DataContainer;
use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

#[AsFilterElement(alias: ArchiveElement::TYPE, formType: ChoiceType::class)]
class ArchiveElement extends BelongsToRelationElement implements FormTypeOptionsContract, PaletteContract
{
    public const TYPE = 'flare_archive';

    public function __construct(
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
    ) {}

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $submittedWhitelist = $context->getSubmittedData();
        $filterModel = $context->getFilterModel();
        $inferrer = new PtableInferrer($filterModel, $context->getListModel());

        if (\is_array($submittedWhitelist)) {
            $submittedWhitelist = \array_filter($submittedWhitelist, static fn($v) => $v && \is_scalar($v));
        }

        if ($inferrer->getDcaMainPtable())
        {
            if (!$whitelist = StringUtil::deserialize($filterModel->whitelistParents)) {
                throw new FilterException('No whitelisted parents defined.');
            }

            if (\is_array($submittedWhitelist) && !empty($submittedWhitelist))
            {
                $whitelist = \array_intersect($whitelist, $submittedWhitelist);

                if (empty($whitelist))
                {
                    $qb->blockList();
                    return;
                }
            }
            elseif (\is_scalar($submittedWhitelist) && $submittedWhitelist = (int) $submittedWhitelist)
            {
                if (!\in_array($submittedWhitelist, $whitelist))
                {
                    $qb->blockList();
                    return;
                }

                $whitelist = [$submittedWhitelist];
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

        $palettes = [];

        if ($inferrer->getDcaMainPtable())
        {
            $palettes[] = '{archive_legend},whitelistParents,formatLabel';
        }
        elseif ($inferrer->isDcaDynamicPtable())
        {
            $palettes[] = '{archive_legend},groupWhitelistParents';
        }

        if (!$filterModel->intrinsic)
        {
            $palette = '{form_legend},isMandatory,isMultiple,isExpanded,hasEmptyOption,';

            if ($filterModel->hasEmptyOption) {
                $palette .= 'formatEmptyOption,';
            }

            $palette .= 'preselect,';

            $palettes[] = $palette;
        }

        return empty($palettes) ? null : Str::mergePalettes(...$palettes);
    }

    /**
     * @throws FilterException
     */
    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        $filterModel = $context->getFilterModel();
        $inferrer = new PtableInferrer($filterModel, $context->getListModel());

        $choices->enable();

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $label = ($filterModel->formatLabel === 'custom')
                ? ($filterModel->formatLabelCustom)
                : ($filterModel->formatLabel ?: null);

            $choices->setLabel($label);

            if ($filterModel->hasEmptyOption)
            {
                $emptyOptionLabel = ($filterModel->formatEmptyOption === 'custom')
                    ? ($filterModel->formatEmptyOptionCustom)
                    : ($filterModel->formatEmptyOption ?: null);

                $choices->add('', $emptyOptionLabel);
            }

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
                'required' => $filterModel->isMandatory,
                'multiple' => $filterModel->isMultiple,
                'expanded' => $filterModel->isExpanded,
            ];
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            \dump('dynamic ptable');

            throw new FilterException('Not yet implemented.');
        }

        throw new FilterException('No valid ptable found.');
    }

    #[AsFilterCallback(self::TYPE, 'fields.preselect.load')]
    public function onLoad_preselect(
        mixed          $value,
        ?DataContainer $dc,
        FilterModel    $filterModel,
        ListModel      $listModel
    ): mixed {
        $dca = &$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field];

        $inferrer = new PtableInferrer($filterModel, $listModel);
        $choices = $this->choicesBuilderFactory
            ->createChoicesBuilder()
            ->setModelSuffix('[%id%]')
            ->enable();

        $dca['inputType'] = 'select';
        $dca['eval']['multiple'] = $filterModel->isMultiple;
        $dca['eval']['chosen'] = true;
        $dca['eval']['includeBlankOption'] = true;
        $dca['options_callback'] = static fn(DataContainer $dc) => $choices->buildOptions();

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            if (!$whitelist = StringUtil::deserialize($filterModel->whitelistParents)) {
                return $value;
            }

            $pClass = Model::getClassFromTable($ptable);

            if (!class_exists($pClass)) {
                return $value;
            }

            $parents = $pClass::findMultipleByIds($whitelist);

            foreach ($parents as $parent) {
                $choices->add($parent->id, $parent);
            }

            return $value;
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            // todo
        }

        return $value;
    }
}