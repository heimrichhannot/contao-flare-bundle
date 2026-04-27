<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

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
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\InferPtable\Factory\PtableInferrableFactory;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrableInterface;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;

#[AsFilterElement(type: self::TYPE, formType: ChoiceType::class)]
class ArchiveElement extends AbstractFilterElement implements HydrateFormContract, IntrinsicValueContract
{
    public const TYPE = 'flare_archive';

    private array $_inferrer = [];

    public function __construct(
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
        private readonly BelongsToRelationElement $relationElement,
    ) {}

    /**
     * @throws FilterException
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        /** @var Model[] $selectedModels */
        $selectedModels = $inv->getValue() ?? [];
        $inferrer = $this->getPtableInferrer($inv->list);

        if (!$selectedModels)
        {
            if ($inv->filter->useWhitelistForOptionsOnly) {
                return;
            }

            $qb::abort();
        }

        if ($inferrer->getDcaMainPtable())
        {
            if (!$pids = \array_column($selectedModels, 'id')) {
                throw new FilterException('No valid parent archive ids extracted.');
            }

            $qb->where($qb->expr()->in($qb->column('pid'), ':pids'))
                ->setParameter('pids', $pids, ArrayParameterType::INTEGER);

            return;
        }

        if (!$inferrer->isDcaDynamicPtable())
            // no valid ptable available
        {
            throw new FilterException('No valid ptable found.');
        }

        /**
         * ## = We are dealing with a _dynamic ptable_. ⇒
         */

        $grouped = [];
        $selectedModels = \array_filter((array) $selectedModels);

        foreach ($selectedModels as $item)
        {
            if ($item instanceof Model) {
                $grouped[$item::getTable()][] = $item->id;
            }
        }

        $this->relationElement->filterDynamicPtableField(
            qb: $qb,
            filter: $inv->filter,
            fieldDynamicPtable: 'ptable',
            fieldPid: 'pid',
            submittedData: $grouped,
        );
    }

    protected function getWhitelistedParentIds(ListSpecification $list, FilterDefinition $filter): ?array
    {
        $inferrer = $this->getPtableInferrer($list);

        if ($inferrer->getDcaMainPtable())
        {
            return $this->getParentIdsFromWhitelistBlob($filter->whitelistParents);
        }

        if (!$inferrer->isDcaDynamicPtable())
            // no valid ptable available
        {
            return [];
        }

        return $this->getParentIdsFromGroupWhitelistBlob($filter->groupWhitelistParents);
    }

    protected function getWhitelistedParents(ListSpecification $list, FilterDefinition $filter): array
    {
        $inferrer = $this->getPtableInferrer($list);

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

        return $this->getParentsFromGroupWhitelistBlob($filter->groupWhitelistParents);
    }

    /**
     * @return Model[]
     */
    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): array
    {
        return $this->getWhitelistedParents($list, $filter);
    }

    /**
     * @return Model[]
     */
    public function processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): array
    {
        $values = $this->normalizeFilterValue($value);

        // If no value is selected, or the empty option is selected, and the filter
        // applies not only to form options, we must filter by all whitelisted archives.
        $useFullWhitelist = (!$values || $values === true) && !$filter->useWhitelistForOptionsOnly;

        if ($useFullWhitelist) {
            return $this->getWhitelistedParents($list, $filter);
        }

        if (!$values || $values === true) {
            return [];
        }

        if (!$allowedParentIds = $this->getWhitelistedParentIds($list, $filter)) {
            return [];
        }

        if (\array_is_list($allowedParentIds))
        {
            $allowedLookup = \array_flip($allowedParentIds);

            return \array_values(\array_filter(
                $values,
                static fn (Model $model): bool => isset($allowedLookup[$model->id]),
            ));
        }

        \array_walk($allowedParentIds, static fn (array &$ids): array => $ids = \array_flip($ids));
        /**
         * @var array<string, array<int, int>> $allowedParentIds 2D array mapping table names to parent IDs as keys.
         *   I.e., flips the nested arrays to be lookup tables for efficient filtering.
         * @example $allowedParentIds = array{
         *    'tl_news_archive': [
         *      5:  0,  // where 5 is the ID of the news archive
         *      8:  1,  // ID 8
         *      12: 2,  // ID 12
         *    ]
         *  }
         */
        return \array_values(\array_filter(
            $values,
            static fn (Model $model): bool => isset($allowedParentIds[$model::getTable()][$model->id]),
        ));
    }

    /**
     * @return Model[]|true|null Returns true if the empty option is selected, null if no value is selected.
     */
    protected function normalizeFilterValue(mixed $value): array|true|null
    {
        if (!$value) {
            return null;
        }

        if (!\is_iterable($value)) {
            $value = [$value];
        }

        $arr = [];

        foreach ($value as $v) {
            if ($v === ChoicesBuilder::EMPTY_CHOICE) {
                return true;
            }

            if ($v instanceof Model) {
                $arr[] = $v;
            }
        }

        return $arr;
    }

    private function getPtableInferrer(ListSpecification $list): PtableInferrer
    {
        $cacheKey = $list->hash();

        if (isset($this->_inferrer[$cacheKey])) {
            return $this->_inferrer[$cacheKey];
        }

        $inferrable = PtableInferrableFactory::createFromListModelLike($list);
        return $this->_inferrer[$cacheKey] = new PtableInferrer($inferrable, $list->dc);
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
            $palettes[] = '{archive_legend},whitelistParents,formatLabel,useWhitelistForOptionsOnly';
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        elseif ($inferrer->isDcaDynamicPtable())
        {
            $palettes[] = '{archive_legend},groupWhitelistParents,useWhitelistForOptionsOnly';
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
    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
    {
        $filter = $event->filter;

        $dataSource = $filter->getDataSource();
        if (!$dataSource instanceof PtableInferrableInterface) {
            return;
        }

        $inferrer = new PtableInferrer($dataSource, $event->list->dc);

        $choices = $event->choicesBuilder->enable();

        $event->options['required'] = (bool) $filter->isMandatory;
        $event->options['multiple'] = (bool) $filter->isMultiple;
        $event->options['expanded'] = (bool) $filter->isExpanded;

        if ($filter->hasEmptyOption)
        {
            $emptyOptionLabel = ($filter->formatEmptyOption === 'custom')
                ? $filter->formatEmptyOptionCustom
                : $filter->formatEmptyOption;

            $emptyOptionValue = ($filter->isExpanded && $filter->isMultiple)
                ? ChoicesBuilder::EMPTY_CHOICE_VALUE_ALTERNATIVE
                : null;

            $choices->setEmptyOption($emptyOptionLabel ?: true, $emptyOptionValue);
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
                $choices->add((string) $parent->id, $parent);
            }

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
                    $choices->add(\sprintf('%s.%s', $table, $parent->id), $parent);
                }
            }
        }

        return $value;
    }

    /**
     * @return int[]|null
     */
    protected function getParentIdsFromWhitelistBlob(?string $blob): ?array
    {
        if (!$whitelist = StringUtil::deserialize($blob, true)) {
            return null;
        }

        if (!$whitelist = \array_unique(\array_filter(\array_map('\intval', $whitelist)))) {
            return null;
        }

        return \array_values($whitelist);
    }

    protected function getParentsFromWhitelistBlob(?string $table, ?string $blob): ?Collection
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

        $whitelist = $this->getParentIdsFromWhitelistBlob($blob);

        return $parentModelClass::findMultipleByIds($whitelist);
    }

    /**
     * @return array<string, int[]> Returns an array mapping table names to parent IDs
     */
    protected function getParentIdsFromGroupWhitelistBlob(?string $blob): array
    {
        $groupWhitelist = StringUtil::deserialize($blob, true);

        $tableToParentIds = [];

        foreach ($groupWhitelist as $group)
        {
            if (!\is_array($group)) {
                continue;
            }

            $table = $group['tablePtable'] ?? null;
            $whitelistParentsBlob = $group['whitelistParents'] ?? null;

            if (!$table || !$whitelistParentsBlob) {
                continue;
            }

            if (!$parentIds = $this->getParentIdsFromWhitelistBlob($whitelistParentsBlob)) {
                continue;
            }

            $tableToParentIds[$table] ??= [];
            \array_push($tableToParentIds[$table], ...$parentIds);
        }

        return $tableToParentIds;
    }

    /**
     * @param string|null $blob
     * @return Model[]
     */
    protected function getParentsFromGroupWhitelistBlob(?string $blob): array
    {
        $tableToParentIds = $this->getParentIdsFromGroupWhitelistBlob($blob);

        $allParents = [];

        foreach ($tableToParentIds as $table => $parentIds)
        {
            if (!$parentModelClass = Model::getClassFromTable($table)) {
                continue;
            }

            if (!\class_exists($parentModelClass)) {
                continue;
            }

            if (!$parentIds = \array_unique($parentIds)) {
                continue;
            }

            if (!$coll = $parentModelClass::findMultipleByIds($parentIds)) {
                continue;
            }

            \array_push($allParents, ...$coll->getModels());
        }

        return $allParents;
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
        $fetch = [];

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

            $fetch[$table] ??= [];
            $fetch[$table][] = (int) $id;
        }

        foreach ($fetch as $table => $ids)
        {
            if (!$ids = \array_unique($ids)) {
                continue;
            }

            if (!$modelClass = Model::getClassFromTable($table)) {
                continue;
            }

            if (!\class_exists($modelClass)) {
                continue;
            }

            if (!$models = $modelClass::findMultipleByIds($ids)?->getModels()) {
                continue;
            }

            \array_push($data, ...$models);
        }

        $field->setData($data);
    }
}