<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IneffectiveFilterContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\ArchivePredicate;
use HeimrichHannot\FlareBundle\Filter\Predicate\BelongsToRelationPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\InferPtable\Factory\PtableInferrableFactory;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

#[AsFilterElement(type: self::TYPE, value: ChoiceValue::class)]
class ArchiveFilterElement extends AbstractFilterElement implements ChoiceSourceContract, IneffectiveFilterContract
{
    public const TYPE = 'flare_archive';

    /** @var array<string, PtableInferrer> */
    private array $_inferrer = [];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('whitelist_parents')->default([])->allowedTypes('int[]');
        $resolver->define('group_whitelist_parents')->default([])->allowedTypes('array');
        $resolver->define('use_whitelist_for_options_only')->default(false)->allowedTypes('bool');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('whitelist_parents', $this->normalizeIds($model->elm_whitelistParents))
            ->set('group_whitelist_parents', $this->normalizeGroups($model->elm_groupWhitelistParents))
            ->set('use_whitelist_for_options_only', (bool) $model->elm_useWhitelistForOptionsOnly);
    }

    /**
     * @throws FilterException
     */
    public function buildChoices(ChoicesBuilder $choices, ElementScope $scope): void
    {
        $inferrer = $this->getPtableInferrer($scope->list);

        if (!self::isControlled($scope->filter)) {
            $this->buildAllChoices($choices, $inferrer, $scope->list->dc);
            return;
        }

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $parents = $this->fetchParents($ptable, $scope->get('whitelist_parents'));

            if (!$parents) {
                throw new FilterException($this->trans('errors.archive.no_parents'), method: __METHOD__);
            }

            foreach ($parents as $parent)
            {
                $choices->add(ContaoModelChoice::from($parent));
            }

            return;
        }

        if (!$inferrer->isDcaDynamicPtable())
            // no valid ptable available
        {
            throw new FilterException($this->trans('errors.archive.no_ptable'), method: __METHOD__);
        }

        /**
         * ## We are dealing with a _dynamic ptable_ henceforth.
         */

        if (!$groups = $scope->get('group_whitelist_parents'))
        {
            throw new FilterException($this->trans('errors.archive.no_parents'), method: __METHOD__);
        }

        foreach ($groups as $group)
        {
            $table = $group['table'];

            foreach ($this->fetchParents($table, $group['ids'])?->getModels() ?? [] as $parent) {
                $choices->add(ContaoModelChoice::from($parent));
            }

            $choices->setLabelForTable($group['label'], $table);
        }

        if (!$choices->count()) {
            throw new FilterException($this->trans('errors.archive.no_valid_parents'), method: __METHOD__);
        }

        $choices->setModelSuffix('(%@name%)');
    }

    /**
     * Whether the filter has a control: then the whitelist bounds it, without one its value is the restriction.
     */
    private static function isControlled(Filter $filter): bool
    {
        return $filter->control !== null;
    }

    /**
     * Without a control the value is the restriction, so it may be any parent: every record of the static ptable, or
     * of every table the dynamic ptable column holds. The whitelist config is ignored, whatever its source.
     *
     * Known limitation: for a dynamic ptable (e.g. tl_content), every model of every parent table is loaded for the
     * backend value field; accepted for now (see the design of 2026-10-29, #9).
     *
     * @throws FilterException
     */
    private function buildAllChoices(ChoicesBuilder $choices, PtableInferrer $inferrer, string $dc): void
    {
        if ($ptable = $inferrer->getDcaMainPtable())
        {
            foreach ($this->fetchAllParentChoices($ptable) as $choice) {
                $choices->add($choice);
            }

            return;
        }

        if (!$inferrer->isDcaDynamicPtable()) {
            throw new FilterException($this->trans('errors.archive.no_ptable'), method: __METHOD__);
        }

        foreach ($this->fetchDynamicParentTables($dc) as $table)
        {
            foreach ($this->fetchAllParentChoices($table) as $choice) {
                $choices->add($choice);
            }
        }

        $choices->setModelSuffix('(%@name%)');
    }

    /**
     * Reads a canonical key "<table>.<id>" with a positive integer id.
     */
    public function choiceFromKey(string $key): ?ContaoModelChoice
    {
        if (!\preg_match('/^([^.]+)\.([1-9]\d*)\z/', $key, $matches)) {
            return null;
        }

        return ContaoModelChoice::ref($matches[1], (int) $matches[2]);
    }

    /**
     * Without a control the fixed value alone restricts, so a filter with neither restricts nothing.
     */
    public function getIneffectiveReason(Filter $filter): ?TranslatableInterface
    {
        if (self::isControlled($filter) || $filter->value !== null) {
            return null;
        }

        return new TranslatableMessage('filter.info.ineffective.archive_without_value', domain: 'flare');
    }

    /**
     * Works on parent ids grouped by table; no model is loaded on the query path (§7.4).
     *
     * @throws FilterException
     */
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $value = $this->expectValue($value, ChoiceValue::class);
        $inferrer = $this->getPtableInferrer($scope->list);

        if (!self::isControlled($scope->filter)) {
            $this->buildFixedContext($builder, $inferrer, $value);
            return;
        }

        $allowed = $this->getAllowedParentIds($inferrer, $scope);

        if (!$value && $scope->get('use_whitelist_for_options_only')) {
            return;
        }

        // Without a value, the whole whitelist applies; a value is narrowed to it, so stale or
        // foreign keys never widen the list.
        $parentIds = $value ? $this->selectParentIds($value, $allowed) : \array_filter($allowed);

        if (!$parentIds) {
            $builder->abort();
        }

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $builder->addPredicate(ArchivePredicate::class, [
                'field' => 'pid',
                'parent_ids' => $parentIds[$ptable] ?? $builder->abort(),
            ]);

            return;
        }

        $builder->addPredicate(BelongsToRelationPredicate::class, [
            'field_pid' => 'pid',
            'field_dynamic_ptable' => 'ptable',
            'parent_groups' => $this->getDynamicParentGroups($scope->get('group_whitelist_parents')),
            'submitted_data' => $parentIds,
        ]);
    }

    /**
     * Without a control the fixed value alone restricts: the whitelist config is ignored, whatever its source, and
     * without a value nothing is restricted.
     *
     * @throws FilterException
     */
    private function buildFixedContext(
        FilterContextBuilderInterface $builder,
        PtableInferrer                $inferrer,
        ?ChoiceValue                  $value,
    ): void {
        $ptable = $inferrer->getDcaMainPtable();

        if (!$ptable && !$inferrer->isDcaDynamicPtable()) {
            throw new FilterException($this->trans('errors.archive.no_ptable'), method: __METHOD__);
        }

        if (!$value) {
            return;
        }

        $selected = $this->selectParentIds($value);

        if ($ptable)
        {
            $builder->addPredicate(ArchivePredicate::class, [
                'field' => 'pid',
                'parent_ids' => $selected[$ptable] ?? $builder->abort(),
            ]);

            return;
        }

        $groups = [];

        foreach ($selected as $table => $ids) {
            $groups[] = ['table' => $table, 'ids' => $ids];
        }

        $builder->addPredicate(BelongsToRelationPredicate::class, [
            'field_pid' => 'pid',
            'field_dynamic_ptable' => 'ptable',
            'parent_groups' => $groups,
        ]);
    }

    /**
     * The selected parent ids, grouped by table in the value's order (a {@see ChoiceValue} is deduplicated and sorted
     * by canonical key); bounded by the allowed ids when given, so stale or foreign keys never widen the list.
     *
     * @param array<string, list<int>>|null $allowed The allowed ids by table, or null for no bound.
     * @return array<string, list<int>>
     */
    private function selectParentIds(ChoiceValue $value, ?array $allowed = null): array
    {
        $selected = [];

        foreach ($value->choices as $choice)
        {
            if (!$choice instanceof ContaoModelChoice) {
                continue;
            }

            if ($allowed === null || \in_array($choice->id, $allowed[$choice->table] ?? [], true)) {
                $selected[$choice->table][] = $choice->id;
            }
        }

        return $selected;
    }

    /**
     * The whitelist, grouped by table: the main ptable's whitelist (static ptable), or the group
     * whitelists (dynamic ptable).
     *
     * @return array<string, list<int>>
     * @throws FilterException
     */
    private function getAllowedParentIds(PtableInferrer $inferrer, ElementScope $scope): array
    {
        if ($ptable = $inferrer->getDcaMainPtable()) {
            return [$ptable => $scope->get('whitelist_parents')];
        }

        if (!$inferrer->isDcaDynamicPtable()) {
            throw new FilterException($this->trans('errors.archive.no_ptable'), method: __METHOD__);
        }

        $grouped = [];

        foreach ($scope->get('group_whitelist_parents') as $group)
        {
            $grouped[$group['table']] = \array_values(\array_unique(
                \array_merge($grouped[$group['table']] ?? [], $group['ids']),
            ));
        }

        return $grouped;
    }

    /**
     * @return array<int, array{table: string, ids: int[]}>
     */
    protected function getDynamicParentGroups(array $parentGroups): array
    {
        $groups = [];

        foreach ($parentGroups as $group)
        {
            $groups[] = [
                'table' => $group['table'],
                'ids' => $group['ids'],
            ];
        }

        return $groups;
    }

    /**
     * Depends only on the list's data container and config, so it is cached on exactly those (§9).
     */
    private function getPtableInferrer(ListSpec $list): PtableInferrer
    {
        $cacheKey = \sha1(\serialize([$list->dc, $list->config]));

        return $this->_inferrer[$cacheKey] ??= new PtableInferrer(
            PtableInferrableFactory::createFromConfig($list->config),
            $list->dc,
        );
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        // Without a control the value is the restriction; the whitelist only serves a control.
        if (!($filterModel = $context->filterModel) || (string) $filterModel->control === '') {
            return;
        }

        $inferrer = new PtableInferrer($filterModel, $context->listModel->dc);

        if ($inferrer->getDcaMainPtable())
        {
            $builder->palette('{archive_legend},elm_whitelistParents,elm_useWhitelistForOptionsOnly');
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        elseif ($inferrer->isDcaDynamicPtable())
        {
            $builder->palette('{archive_legend},elm_groupWhitelistParents,elm_useWhitelistForOptionsOnly');
        }
    }

    /**
     * @param int[] $ids
     */
    protected function fetchParents(?string $table, array $ids): ?Collection
    {
        if (!$table || !$ids) {
            return null;
        }

        if (!$parentModelClass = self::findModelClass($table)) {
            return null;
        }

        return $parentModelClass::findMultipleByIds(\array_values($ids));
    }

    /**
     * Every record of the table as a choice; none for a table without a model class.
     *
     * @return list<ContaoModelChoice>
     */
    protected function fetchAllParentChoices(string $table): array
    {
        if (!$modelClass = self::findModelClass($table)) {
            return [];
        }

        return \array_map(
            ContaoModelChoice::from(...),
            $modelClass::findAll()?->getModels() ?? [],
        );
    }

    /**
     * The model class of the table, or null without one: Contao 5 throws for an unregistered table, Contao 4.13
     * guesses a class name that may not exist.
     *
     * @return class-string<Model>|null
     */
    private static function findModelClass(string $table): ?string
    {
        try
        {
            $modelClass = Model::getClassFromTable($table);
        }
        catch (\RuntimeException)
        {
            return null;
        }

        return $modelClass && \class_exists($modelClass) ? $modelClass : null;
    }

    /**
     * The parent tables the dynamic ptable column of the data container holds.
     *
     * @return list<string>
     */
    protected function fetchDynamicParentTables(string $table): array
    {
        $connection = $this->getConnection();

        return \array_values(\array_map('strval', $connection->fetchFirstColumn(\sprintf(
            "SELECT DISTINCT %s FROM %s WHERE %s <> ''",
            $connection->quoteIdentifier('ptable'),
            $connection->quoteIdentifier($table),
            $connection->quoteIdentifier('ptable'),
        ))));
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
