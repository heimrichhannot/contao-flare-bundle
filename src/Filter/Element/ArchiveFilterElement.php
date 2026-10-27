<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
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

#[AsFilterElement(type: self::TYPE, value: ChoiceValue::class)]
class ArchiveFilterElement extends AbstractFilterElement implements ChoiceSourceContract
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

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            $parents = $this->fetchParents($ptable, $scope->get('whitelist_parents'));

            if (!$parents) {
                throw new FilterException(
                    'No whitelisted parents defined or parent table class invalid.',
                    method: __METHOD__,
                );
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
            throw new FilterException('No valid ptable found.', method: __METHOD__);
        }

        /**
         * ## We are dealing with a _dynamic ptable_ henceforth.
         */

        if (!$groups = $scope->get('group_whitelist_parents'))
        {
            throw new FilterException('No whitelisted parents defined.', method: __METHOD__);
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
            throw new FilterException('No valid whitelisted parents defined.', method: __METHOD__);
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
        $allowed = $this->getAllowedParentIds($inferrer, $scope);

        if (!$value && $scope->get('use_whitelist_for_options_only')) {
            return;
        }

        // Without a value, the whole whitelist applies; a value is narrowed to it, so stale or
        // foreign keys never widen the list.
        $parentIds = $value ? $this->intersectSelection($value, $allowed) : \array_filter($allowed);

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
            throw new FilterException('No valid ptable found.', method: __METHOD__);
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
     * @param array<string, list<int>> $allowed
     * @return array<string, list<int>> The selected ids within the allowed ones, grouped by table.
     */
    private function intersectSelection(ChoiceValue $value, array $allowed): array
    {
        $selected = [];

        foreach ($value->choices as $choice)
        {
            if (!$choice instanceof ContaoModelChoice) {
                continue;
            }

            if (\in_array($choice->id, $allowed[$choice->table] ?? [], true)) {
                $selected[$choice->table][] = $choice->id;
            }
        }

        return $selected;
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
        if (!$filterModel = $context->filterModel) {
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
