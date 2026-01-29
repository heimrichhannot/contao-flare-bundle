<?php

namespace HeimrichHannot\FlareBundle\FilterElement\Relation;

use Contao\DataContainer;
use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Util\PtableInferrableFactory;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;

#[AsFilterElement(type: self::TYPE, formType: ChoiceType::class)]
class ArchiveElement extends BelongsToRelationElement implements HydrateFormContract, IntrinsicValueContract
{
    public const TYPE = 'flare_archive';

    public function __construct(
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
    ) {}

    /**
     * @throws FilterException
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        $filerValue = $inv->getValue();

        $inferrable = PtableInferrableFactory::createFromListModelLike($inv->list);
        $inferrer = new PtableInferrer($inferrable, $inv->list->dc);

        if ($filerValue && !\is_array($filerValue)) {
            $filerValue = [$filerValue];
        }

        foreach ($filerValue ?? [] as $value)
        {
            if ($value === ChoicesBuilder::EMPTY_CHOICE) {
                return;
            }

            if (!$value instanceof Model) {
                $qb->abort();
            }
        }

        if ($inferrer->getDcaMainPtable())
        {
            $filerValueIds = \array_map(static fn (Model $model): int => (int) $model->id, $filerValue);
            $whitelist = $filerValueIds;

            if (!$inv->filter->isIntrinsic())
                // Double-check that we are only filtering by whitelisted parents when the filter has a form value
            {
                if (!$whitelist = StringUtil::deserialize($inv->filter->whitelistParents, true)) {
                    throw new FilterException('No whitelisted parents defined.');
                }

                if ($filerValueIds && (!$inv->filter->hasEmptyOption || \count($filerValueIds) > 0))
                    // we expect $filerValue to be an array of parent models
                    // if the empty option is enabled, an empty array is allowed
                {
                    $whitelist = \array_intersect(\array_map('\intval', $whitelist), $filerValueIds);

                    if (!$whitelist)
                    {
                        $qb->abort();
                    }
                }
            }

            $qb->where($qb->expr()->in($qb->column('pid'), ':pidIn'))
                ->setParameter('pidIn', $whitelist, ArrayParameterType::INTEGER);

            return;
        }

        if (!$inferrer->isDcaDynamicPtable())
            // no valid ptable available
        {
            throw new FilterException('No valid ptable found.');
        }

        /**
         * ## We are dealing with a _dynamic ptable_ henceforth.
         */

        $grouped = null;
        $filerValue = \array_filter((array) $filerValue);

        if ($filerValue || (!$inv->filter->isIntrinsic() && $inv->filter->hasEmptyOption))
            // we expect $filerValue to be an array of values formatted {table}.{id}
            // if hasEmptyOption is enabled, an empty array is allowed
        {
            $grouped = [];

            foreach ($filerValue as $value)
            {
                if ($value instanceof Model) {
                    $grouped[$value::getTable()][] = $value->id;
                }

                if (!\is_string($value) || !\str_contains($value, '.')) {  // skip invalid values
                    continue;
                }

                [$table, $id] = \explode('.', $value, 2);

                $grouped[$table][] = (int) $id;
            }
        }

        $this->filterDynamicPtableField($qb, $inv->filter, 'ptable', 'pid', $grouped);
    }

    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): array
    {
        $inferrable = PtableInferrableFactory::createFromListModelLike($list);
        $inferrer = new PtableInferrer($inferrable, $list->dc);

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $parents = $this->getParentsFromWhitelistBlob($ptable, $filter->whitelistParents);
            return $parents?->getModels() ?? [];
        }

        if (!$inferrer->isDcaDynamicPtable())
            // no valid ptable available
        {
            return [];
        }

        if (!$groupWhitelist = StringUtil::deserialize($filter->groupWhitelistParents, true))
        {
            return [];
        }

        $allParents = [];

        foreach ($groupWhitelist as $group)
        {
            $table = $group['tablePtable'] ?? null;
            $whitelistParents = $group['whitelistParents'] ?? null;

            if (!$table || !$whitelistParents) {
                continue;
            }

            $parents = $this->getParentsFromWhitelistBlob($table, $whitelistParents)?->getModels() ?? [];

            \array_push($allParents, ...$parents);
        }

        return $allParents;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        if (!$filterModel = $config->getFilterModel()) {
            return null;
        }

        $inferrer = new PtableInferrer($filterModel, $config->getListModel()->dc);

        $palettes = [];

        if ($inferrer->getDcaMainPtable())
        {
            $palettes[] = '{archive_legend},whitelistParents,formatLabel';
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
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

        if (!$palettes) {
            return null;
        }

        return Str::mergePalettes(...$palettes);
    }

    /**
     * @throws FilterException
     */
    public function onFormTypeOptionsEvent(FilterElementFormTypeOptionsEvent $event): void
    {
        $filter = $event->filterDefinition;

        if (!$filterModel = $filter->getSourceFilterModel()) {
            return;
        }

        $inferrer = new PtableInferrer($filterModel, $event->listDefinition->dc);

        $choices = $event->getChoicesBuilder()->enable();

        if ($filter->hasEmptyOption)
        {
            $emptyOptionLabel = ($filter->formatEmptyOption === 'custom')
                ? $filter->formatEmptyOptionCustom
                : $filter->formatEmptyOption;

            $choices->setEmptyOption($emptyOptionLabel ?: true);
        }

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $label = ($filter->formatLabel === 'custom')
                ? $filter->formatLabelCustom
                : $filter->formatLabel;

            $label = $label ?: null;

            $choices->setLabel($label);

            $parents = $this->getParentsFromWhitelistBlob($ptable, $filter->whitelistParents);

            if (!$parents) {
                throw new FilterException('No whitelisted parents defined or parent table class invalid.');
            }

            foreach ($parents as $parent)
            {
                $choices->add($parent->id, $parent);
            }

            $event->options['required'] = (bool) $filter->isMandatory;
            $event->options['multiple'] = (bool) $filter->isMultiple;
            $event->options['expanded'] = (bool) $filter->isExpanded;

            return;
        }

        if (!$inferrer->isDcaDynamicPtable())
            // no valid ptable available
        {
            throw new FilterException('No valid ptable found.');
        }

        /**
         * ## We are dealing with a _dynamic ptable_ henceforth.
         */

        if (!$groupWhitelist = StringUtil::deserialize($filter->groupWhitelistParents, true))
        {
            throw new FilterException('No whitelisted parents defined.');
        }

        foreach ($groupWhitelist as $group)
        {
            $table = $group['tablePtable'] ?? null;
            $whitelistParents = $group['whitelistParents'] ?? null;

            if (!$table || !$whitelistParents) {
                continue;
            }

            $parents = $this->getParentsFromWhitelistBlob($table, $whitelistParents);

            foreach ($parents as $parent)
            {
                $choices->add(\sprintf('%s.%s', $table, $parent->id), $parent);
            }

            $formatLabel = $group['formatLabel'] ?? null;
            $formatLabel = ($formatLabel === 'custom')
                ? ($group['formatLabelCustom'] ?? null)
                : $formatLabel;
            $formatLabel = $formatLabel ?: null;

            $choices->setLabelForTable($formatLabel, $table);
        }

        if (!$choices->count()) {
            throw new FilterException('No valid whitelisted parents defined.');
        }

        $choices->setModelSuffix('(%@name%)');

        $event->options['required'] = (bool) $filter->isMandatory;
        $event->options['multiple'] = (bool) $filter->isMultiple;
        $event->options['expanded'] = (bool) $filter->isExpanded;
    }

    #[AsFilterCallback(self::TYPE, 'fields.preselect.load')]
    public function onLoad_preselect(
        mixed          $value,
        ?DataContainer $dc,
        FilterModel    $filterModel,
        ListModel $listModel
    ): mixed {
        if (!$dc) {
            return [];
        }

        $dca = &$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field];

        $inferrer = new PtableInferrer($filterModel, $listModel->dc);
        $choices = $this->choicesBuilderFactory
            ->createChoicesBuilder()
            ->setModelSuffix('[%id%]')
            ->enable();

        $dca['inputType'] = 'select';
        $dca['eval']['multiple'] = $filterModel->isMultiple;
        $dca['eval']['chosen'] = true;
        $dca['eval']['includeBlankOption'] = true;
        $dca['options_callback'] = static fn (DataContainer $dc): array => $choices->buildOptions();

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            if (!$parents = $this->getParentsFromWhitelistBlob($ptable, $filterModel->whitelistParents)) {
                return $value;
            }

            foreach ($parents as $parent)
            {
                if (!$parent instanceof Model) {
                    continue;
                }

                $choices->add(\sprintf('%s.%s', $ptable, $parent->id), $parent);
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

                foreach ($parents as $parent)
                {
                    if (!$parent instanceof Model) {
                        continue;
                    }

                    $choices->add(\sprintf('%s.%s', $table, $parent->id), $parent);
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

        if (!$parentModelClass = Model::getClassFromTable($table)) {
            return null;
        }

        if (!\class_exists($parentModelClass)) {
            return null;
        }

        if (!$whitelist = StringUtil::deserialize($blob, true)) {
            return null;
        }

        if (!$whitelist = \array_unique(\array_filter(\array_map('\intval', $whitelist)))) {
            return null;
        }

        return $parentModelClass::findMultipleByIds($whitelist);
    }

    public function hydrateForm(FormInterface $field, ListSpecification $list, FilterDefinition $filter): void
    {
        if (!$preselect = StringUtil::deserialize($filter->preselect ?: null, true))
        {
            return;
        }

        $ptableInferrer = static function () use (&$ptableInferrer, $list): PtableInferrer {
            $inferrable = PtableInferrableFactory::createFromListModelLike($list);
            $inferrer = new PtableInferrer($inferrable, $list->dc);
            $ptableInferrer = static fn (): PtableInferrer => $inferrer;
            return $inferrer;
        };

        $ptable = static function () use (&$ptable, $ptableInferrer): string {
            $pt = $ptableInferrer()->getDcaMainPtable();
            $ptable = static fn (): string => $pt;
            return $pt;
        };

        $data = [];

        foreach ($preselect as $entity)
        {
            if ($entity instanceof Model) {
                $data[] = $entity;
                continue;
            }

            if (\is_numeric($entity))
            {
                if (!$ptable() || !$modelClass = Model::getClassFromTable($ptable())) {
                    continue;
                }

                if (!\class_exists($modelClass)) {
                    continue;
                }

                if (!$model = $modelClass::findByPk($entity)) {
                    continue;
                }

                $data[] = $model;
                continue;
            }

            if (!\is_string($entity) || !\str_contains($entity, '.')) {
                continue;
            }

            [$table, $id] = \explode('.', $entity, 2);

            if (!$modelClass = Model::getClassFromTable($table)) {
                continue;
            }

            if (!\class_exists($modelClass)) {
                continue;
            }

            if ($model = $modelClass::findByPk($id)) {
                $data[] = $model;
            }
        }

        $field->setData($data);
    }
}