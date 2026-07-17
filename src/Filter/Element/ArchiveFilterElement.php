<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Type\ArchiveFilterType;
use HeimrichHannot\FlareBundle\Filter\Type\BelongsToRelationFilterType;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\InferPtable\Factory\PtableInferrableFactory;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE)]
class ArchiveFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_archive';

    private array $_inferrer = [];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('whitelist_parents')->default([])->allowedTypes('int[]');
        $resolver->define('group_whitelist_parents')->default([])->allowedTypes('array');
        $resolver->define('use_whitelist_for_options_only')->default(false)->allowedTypes('bool');
        $resolver->define('format_label')->default(null)->allowedTypes('string', 'null');
        $resolver->define('has_empty_option')->default(false)->allowedTypes('bool');
        $resolver->define('format_empty_option')->default(null)->allowedTypes('string', 'null');
        $resolver->define('is_mandatory')->default(false)->allowedTypes('bool');
        $resolver->define('is_multiple')->default(false)->allowedTypes('bool');
        $resolver->define('is_expanded')->default(false)->allowedTypes('bool');
        $resolver->define('preselect')->default([])->allowedTypes('array');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $formatLabel = $model->formatLabel === 'custom'
            ? $model->formatLabelCustom
            : $model->formatLabel;

        $formatEmptyOption = $model->formatEmptyOption === 'custom'
            ? $model->formatEmptyOptionCustom
            : $model->formatEmptyOption;

        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('whitelist_parents', $this->normalizeIds($model->whitelistParents))
            ->set('group_whitelist_parents', $this->normalizeGroups($model->groupWhitelistParents))
            ->set('use_whitelist_for_options_only', (bool) $model->useWhitelistForOptionsOnly)
            ->set('format_label', $formatLabel ?: null)
            ->set('has_empty_option', (bool) $model->hasEmptyOption)
            ->set('format_empty_option', $formatEmptyOption ?: null)
            ->set('is_mandatory', (bool) $model->isMandatory)
            ->set('is_multiple', (bool) $model->isMultiple)
            ->set('is_expanded', (bool) $model->isExpanded)
            ->set('preselect', StringUtil::deserialize($model->preselect ?: null, true));
    }

    /**
     * @throws FilterException
     */
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
    {
        $config = $context->config;

        if ($config['intrinsic']) {
            return;
        }

        $inferrer = $this->getPtableInferrer($context->list);

        $formOptions = [
            'label' => false,
            'required' => $config['is_mandatory'],
            'multiple' => $config['is_multiple'],
            'expanded' => $config['is_expanded'],
        ];

        $data = $this->buildPreselectData($context->list, $config['preselect']);
        if (!\is_null($data) && \count($data)) {
            $formOptions['data'] = $data;
        }

        $choices = $this->createChoicesBuilder()->applyFormOptions($formOptions);
        $builder->setAttribute('flare.choices_builder', $choices);

        $builder->single(ChoiceType::class, $formOptions);

        if ($config['has_empty_option'])
        {
            $emptyOptionValue = ($config['is_expanded'] && $config['is_multiple'])
                ? ChoicesBuilder::EMPTY_CHOICE_VALUE_ALTERNATIVE
                : null;

            $choices->setEmptyOption($config['format_empty_option'] ?: true, $emptyOptionValue);
        }

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $choices->setLabel($config['format_label'] ?: null);

            $parents = $this->fetchParents($ptable, $config['whitelist_parents']);

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

        if (!$groups = $config['group_whitelist_parents'])
        {
            throw new FilterException('No whitelisted parents defined.');
        }

        foreach ($groups as $group)
        {
            $table = $group['table'];

            $parents = $this->fetchParents($table, $group['ids'])?->getModels() ?? [];

            foreach ($parents as $parent) {
                $choices->add(\sprintf('%s.%s', $table, $parent->id), $parent);
            }

            $choices->setLabelForTable($group['label'], $table);
        }

        if (!$choices->count()) {
            throw new FilterException('No valid whitelisted parents defined.');
        }

        $choices->setModelSuffix('(%@name%)');
    }

    /**
     * @throws FilterException
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
        $config = $context->config;

        /** @var Model[] $selectedModels */
        $selectedModels = $config['intrinsic']
            ? $this->getWhitelistedParents($context->list, $config)
            : $this->processRuntimeValue($values[FilterContext::SINGLE_VALUE] ?? null, $context->list, $config);

        $inferrer = $this->getPtableInferrer($context->list);

        if (!$selectedModels)
        {
            if ($config['use_whitelist_for_options_only']) {
                return;
            }

            $builder->abort();
        }

        if ($inferrer->getDcaMainPtable())
        {
            if (!$pids = \array_column($selectedModels, 'id')) {
                throw new FilterException('No valid parent archive ids extracted.');
            }

            $builder->add(ArchiveFilterType::class, [
                'field' => 'pid',
                'parent_ids' => $pids,
            ]);

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

        $builder->add(BelongsToRelationFilterType::class, [
            'field_pid' => 'pid',
            'field_dynamic_ptable' => 'ptable',
            'parent_groups' => $this->getDynamicParentGroups($config),
            'submitted_data' => $grouped,
        ]);
    }

    /**
     * @return array<int, array{table: string, ids: int[]}>
     */
    protected function getDynamicParentGroups(array $config): array
    {
        $groups = [];

        foreach ($config['group_whitelist_parents'] as $group)
        {
            $groups[] = [
                'table' => $group['table'],
                'ids' => $group['ids'],
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, int[]>|int[] Parent IDs, either flat (main ptable) or mapped by table (dynamic ptable).
     */
    protected function getWhitelistedParentIds(ListSpec $list, array $config): array
    {
        $inferrer = $this->getPtableInferrer($list);

        if ($inferrer->getDcaMainPtable())
        {
            return $config['whitelist_parents'];
        }

        if (!$inferrer->isDcaDynamicPtable())
            // no valid ptable available
        {
            return [];
        }

        $tableToParentIds = [];

        foreach ($config['group_whitelist_parents'] as $group)
        {
            $tableToParentIds[$group['table']] ??= [];
            \array_push($tableToParentIds[$group['table']], ...$group['ids']);
        }

        return $tableToParentIds;
    }

    /**
     * @return Model[]
     */
    protected function getWhitelistedParents(ListSpec $list, array $config): array
    {
        $inferrer = $this->getPtableInferrer($list);

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $parents = $this->fetchParents($ptable, $config['whitelist_parents']);
            return $parents?->getModels() ?? [];
        }

        if (!$inferrer->isDcaDynamicPtable())
            // no valid ptable available
        {
            return [];
        }

        $allParents = [];

        foreach ($this->getWhitelistedParentIds($list, $config) as $table => $parentIds)
        {
            if (!$parentIds = \array_unique($parentIds)) {
                continue;
            }

            if (!$coll = $this->fetchParents((string) $table, $parentIds)) {
                continue;
            }

            \array_push($allParents, ...$coll->getModels());
        }

        return $allParents;
    }

    /**
     * @return Model[]
     */
    public function processRuntimeValue(mixed $value, ListSpec $list, array $config): array
    {
        $values = $this->normalizeFilterValue($value);

        // If no value is selected, or the empty option is selected, and the filter
        // applies not only to form options, we must filter by all whitelisted archives.
        $useFullWhitelist = (!$values || $values === true) && !$config['use_whitelist_for_options_only'];

        if ($useFullWhitelist) {
            return $this->getWhitelistedParents($list, $config);
        }

        if (!$values || $values === true) {
            return [];
        }

        if (!$allowedParentIds = $this->getWhitelistedParentIds($list, $config)) {
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

    private function getPtableInferrer(ListSpec $list): PtableInferrer
    {
        $cacheKey = $list->hash();

        if (isset($this->_inferrer[$cacheKey])) {
            return $this->_inferrer[$cacheKey];
        }

        $inferrable = PtableInferrableFactory::createFromConfig($list->config);
        return $this->_inferrer[$cacheKey] = new PtableInferrer($inferrable, $list->getDataContainerName());
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        if (!$filterModel = $context->filterModel) {
            return;
        }

        $inferrer = new PtableInferrer($filterModel, $context->listModel->dc);

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

        $dca->palette($palettes ? Str::mergePalettes(...$palettes) : null);

        $dca->field('preselect')
            ->inputType('select')
            ->eval([
                'multiple' => (bool) $filterModel->isMultiple,
                'chosen' => true,
                'includeBlankOption' => true,
            ])
            ->options(fn (): array => $this->getPreselectOptions($inferrer, $filterModel->row()));
    }

    /**
     * Builds the backend options for the preselect field from the whitelisted parents.
     *
     * @param array<string, mixed> $row
     */
    private function getPreselectOptions(PtableInferrer $inferrer, array $row): array
    {
        $choices = $this->createChoicesBuilder()->setModelSuffix('[%id%]');

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            if (!$parents = $this->fetchParents($ptable, $this->normalizeIds($row['whitelistParents'] ?? null))) {
                return $choices->buildContaoOptions();
            }

            foreach ($parents as $parent)
            {
                $choices->add(\sprintf('%s.%s', $ptable, $parent->id), $parent);
            }

            return $choices->buildContaoOptions();
        }

        if ($inferrer->isDcaDynamicPtable())
        {
            $choices->setModelSuffix('[%@table%.id=%id%]');

            foreach ($this->normalizeGroups($row['groupWhitelistParents'] ?? null) as $group)
            {
                if (!$parents = $this->fetchParents($group['table'], $group['ids'])) {
                    continue;
                }

                foreach ($parents as $parent)
                {
                    $choices->add(\sprintf('%s.%s', $group['table'], $parent->id), $parent);
                }
            }
        }

        return $choices->buildContaoOptions();
    }

    /**
     * Resolves the configured preselection (numeric IDs or "table.id" references) into models,
     * to be used as the choice field's initial data.
     *
     * @return Model[]|null
     */
    private function buildPreselectData(ListSpec $list, array $preselect): ?array
    {
        if (!$preselect) {
            return null;
        }

        $ptableInferrer = function () use (&$ptableInferrer, $list): PtableInferrer {
            $inferrer = $this->getPtableInferrer($list);
            $ptableInferrer = static fn (): PtableInferrer => $inferrer;
            return $inferrer;
        };

        $ptable = static function () use (&$ptable, $ptableInferrer): string {
            $pt = (string) $ptableInferrer()->getDcaMainPtable();
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

        return $data;
    }

    /**
     * @param int[] $ids
     */
    protected function fetchParents(?string $table, array $ids): ?Collection
    {
        if (!$table || !$ids) {
            return null;
        }

        if (!$parentModelClass = Model::getClassFromTable($table)) {
            return null;
        }

        if (!\class_exists($parentModelClass)) {
            return null;
        }

        return $parentModelClass::findMultipleByIds(\array_values($ids));
    }

    /**
     * @return int[]
     */
    private function normalizeIds(mixed $blob): array
    {
        if (!$whitelist = StringUtil::deserialize($blob, true)) {
            return [];
        }

        return \array_values(\array_unique(\array_filter(\array_map('\intval', $whitelist))));
    }

    /**
     * Canonicalizes the serialized group widget blob into a list of
     * `{table: string, ids: int[], label: ?string}` groups.
     *
     * @return array<int, array{table: string, ids: int[], label: string|null}>
     */
    private function normalizeGroups(mixed $blob): array
    {
        $groups = [];

        foreach (StringUtil::deserialize($blob, true) as $group)
        {
            if (!\is_array($group)) {
                continue;
            }

            $table = $group['tablePtable'] ?? null;
            $whitelistParentsBlob = $group['whitelistParents'] ?? null;

            if (!$table || !$whitelistParentsBlob) {
                continue;
            }

            if (!$ids = $this->normalizeIds($whitelistParentsBlob)) {
                continue;
            }

            $formatLabel = $group['formatLabel'] ?? null;
            $formatLabel = ($formatLabel === 'custom')
                ? ($group['formatLabelCustom'] ?? null)
                : $formatLabel;

            $groups[] = [
                'table' => (string) $table,
                'ids' => $ids,
                'label' => $formatLabel ?: null,
            ];
        }

        return $groups;
    }
}
