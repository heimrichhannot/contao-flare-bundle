<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\DataContainer;
use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\HydrateFormContract;
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
use Symfony\Component\Form\FormInterface;

#[AsFilterElement(alias: ArchiveElement::TYPE, formType: ChoiceType::class)]
class ArchiveElement extends BelongsToRelationElement implements FormTypeOptionsContract, HydrateFormContract, PaletteContract
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

        if ($filterModel->hasEmptyOption)
        {
            $emptyOptionLabel = ($filterModel->formatEmptyOption === 'custom')
                ? ($filterModel->formatEmptyOptionCustom)
                : ($filterModel->formatEmptyOption ?: null);

            $choices->add('', $emptyOptionLabel);
        }

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $label = ($filterModel->formatLabel === 'custom')
                ? ($filterModel->formatLabelCustom)
                : ($filterModel->formatLabel ?: null);

            $choices->setLabel($label);

            $parents = $this->getParentsFromWhitelistBlob($ptable, $filterModel->whitelistParents);

            if (!$parents) {
                throw new FilterException('No whitelisted parents defined or parent table class invalid.');
            }

            foreach ($parents as $parent)
            {
                $choices->add($parent->id, $parent);
            }

            return [
                'required' => $filterModel->isMandatory,
                'multiple' => $filterModel->isMultiple,
                'expanded' => $filterModel->isExpanded,
            ];
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            if (!$groupWhitelist = StringUtil::deserialize($filterModel->groupWhitelistParents)) {
                throw new FilterException('No whitelisted parents defined.');
            }

            foreach ($groupWhitelist as $group)
            {
                $table = $group['tablePtable'] ?? null;
                $whitelistParents = $group['whitelistParents'] ?? null;

                if (!$table || !$whitelist = StringUtil::deserialize($whitelistParents)) {
                    continue;
                }

                $pClass = Model::getClassFromTable($table);

                if (!\class_exists($pClass)) {
                    throw new FilterException(\sprintf('Invalid parent table class "%s" of table "%s".', $pClass, $table));
                }

                $parents = $pClass::findMultipleByIds($whitelist);

                foreach ($parents as $parent)
                {
                    $choices->add(\sprintf('%s.%s', $table, $parent->id), $parent);
                }

                $formatLabel = $group['formatLabel'] ?? null;
                $formatLabel = ($formatLabel === 'custom')
                    ? ($group['formatLabelCustom'] ?? null)
                    : ($formatLabel ?: null);

                $choices->setLabel($formatLabel, $table);
            }

            if (!$choices->count())
            {
                throw new FilterException('No valid whitelisted parents defined.');
            }

            $choices->setModelSuffix('(%@name%)');

            return [
                'required' => $filterModel->isMandatory,
                'multiple' => $filterModel->isMultiple,
                'expanded' => $filterModel->isExpanded,
            ];
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
            if (!$parents = $this->getParentsFromWhitelistBlob($ptable, $filterModel->whitelistParents)) {
                return $value;
            }

            foreach ($parents as $parent) {
                $choices->add($parent->id, $parent);
            }

            return $value;
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            $choices->setModelSuffix('[%@table%.id=%id%]');

            if (!$groupWhitelist = StringUtil::deserialize($filterModel->groupWhitelistParents)) {
                return $value;
            }

            foreach ($groupWhitelist as $group)
            {
                $parents = $this->getParentsFromWhitelistBlob(
                    table: $table = $group['tablePtable'] ?? null,
                    blob: $group['whitelistParents'] ?? null
                );

                if (!$parents) {
                    continue;
                }

                foreach ($parents as $parent) {
                    $choices->add(\sprintf('%s.%s', $table, $parent?->id), $parent);
                }
            }
        }

        return $value;
    }

    public function getParentsFromWhitelistBlob(?string $table, ?string $blob): ?Collection
    {
        if (!$table || !$blob) {
            return null;
        }

        if (!$whitelist = StringUtil::deserialize($blob)) {
            return null;
        }

        $pClass = Model::getClassFromTable($table);

        if (!\class_exists($pClass)) {
            return null;
        }

        return $pClass::findMultipleByIds($whitelist);
    }

    public function hydrateForm(FilterContext $context, FormInterface $field): void
    {
        $filterModel = $context->getFilterModel();

        if ($preselect = StringUtil::deserialize($filterModel->preselect ?: null))
        {
            $field->setData($preselect);
        }
    }
}