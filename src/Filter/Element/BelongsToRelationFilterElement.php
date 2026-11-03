<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Message;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\BelongsToRelationPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\InferPtable\Factory\PtableInferrableFactory;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFilterElement(type: self::TYPE)]
class BelongsToRelationFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_relation_belongsTo';

    public function __construct(
        private readonly TranslatorInterface $trans,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field_pid')->required()->allowedTypes('string')
            ->allowedValues(static fn (string $value): bool => $value !== '');
        $resolver->define('which_ptable')->default(null)->allowedTypes('string', 'null');
        $resolver->define('whitelist_parents')->default([])->allowedTypes('array');
        $resolver->define('group_whitelist_parents')->default([])->allowedTypes('array');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $whitelistParents = StringUtil::deserialize($model->elm_whitelistParents);
        $groupWhitelistParents = StringUtil::deserialize($model->elm_groupWhitelistParents);

        $config
            ->set('field_pid', $model->elm_fieldPid ?: null)
            ->set('which_ptable', $model->elm_whichPtable ?: null)
            ->set('whitelist_parents', $whitelistParents ? (array) $whitelistParents : [])
            ->set('group_whitelist_parents', \is_array($groupWhitelistParents) ? $groupWhitelistParents : []);
    }

    /**
     * @throws FilterException
     */
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $fieldPid = $scope->get('field_pid');

        $inferrable = PtableInferrableFactory::createFromConfig($scope->list->config);
        $inferrer = new PtableInferrer($inferrable, $scope->list->dc);

        try
        {
            $ptable = $inferrer->getInferredPtable();
            $fieldDynamicPtable = $inferrer->tryGetDynamicPtableField();
        }
        catch (InferenceException $e)
            // A data container FLARE cannot read is config it cannot honour, not data matching nothing.
        {
            throw new FilterException($e->getMessage(), previous: $e, method: __METHOD__);
        }

        if (\is_string($fieldDynamicPtable))
        {
            $builder->addPredicate(BelongsToRelationPredicate::class, [
                'field_pid' => $fieldPid,
                'field_dynamic_ptable' => $fieldDynamicPtable,
                'parent_groups' => $this->getDynamicParentGroups($scope->get('group_whitelist_parents')),
            ]);

            return;
        }

        if (!$ptable || !$whitelistParents = $scope->get('whitelist_parents')) {
            throw new FilterException('No whitelisted parents.');
        }

        $builder->addPredicate(BelongsToRelationPredicate::class, [
            'field_pid' => $fieldPid,
            'whitelist' => $whitelistParents,
        ]);
    }

    /**
     * @param array $parentGroups Deserialized group whitelist, as stored in the
     *   `group_whitelist_parents` config key.
     */
    public function getDynamicParentGroups(array $parentGroups): array
    {
        $groups = [];

        foreach ($parentGroups as $group)
        {
            if (!($g_tablePtable = $group['tablePtable'] ?? null)
                || !($g_whitelistParents = $group['whitelistParents'] ?? null)
                || !\is_array($g_whitelistParents = StringUtil::deserialize($g_whitelistParents)))
            {
                continue;
            }

            $g_whitelistParents = \array_values(\array_filter($g_whitelistParents));

            if (!$g_whitelistParents) {
                continue;
            }

            $groups[] = [
                'table' => $g_tablePtable,
                'ids' => $g_whitelistParents,
            ];
        }

        return $groups;
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $listModel = $context->listModel;
        $filterModel = $context->filterModel;

        if (!$filterModel)
        {
            Message::addError($this->trans->trans('errors.missing_model', [], 'flare'));
            $builder->palette('');
            return;
        }

        if (!$listModel->dc)
        {
            Message::addError($this->trans->trans('errors.missing_datacontainer', ['%id%' => $listModel->id], 'flare'));
            $builder->palette('');
            return;
        }

        $palette = '{filter_legend},elm_fieldPid,elm_whichPtable';

        $inferrer = new PtableInferrer($filterModel, $listModel->dc);
        $table = $inferrer->getEntityTable();
        $fieldPid = $inferrer->getPidField();

        try
        {
            $ptable = $inferrer->getInferredPtable();

            Message::addInfo(match (true) {
                $inferrer->isAutoInferable() && $ptable => $this->trans->trans('infer_ptable.auto', [
                    '%table%' => $table,
                    '%field%' => $fieldPid,
                    '%ptable%' => $ptable,
                ], 'flare'),
                $inferrer->isAutoDynamicPtable() => $this->trans->trans('infer_ptable.dynamic', [
                    '%table%' => $table,
                ], 'flare'),
                default => $this->trans->trans('infer_ptable.invalid', [
                    '%table%' => $table,
                    '%field%' => $fieldPid,
                ], 'flare')
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

        if ($filterModel->elm_whichPtable === 'dynamic')
        {
            $palette .= ';{archive_legend},elm_groupWhitelistParents';
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        elseif ($ptable ?? null)
        {
            $palette .= ',elm_whitelistParents';
        }

        $builder->palette($palette);
    }
}
