<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Message;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\BelongsToRelationFilterType;
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

    public function isOnlyIntrinsic(): bool
    {
        return true;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('field_pid')->default(null)->allowedTypes('string', 'null');
        $resolver->define('which_ptable')->default(null)->allowedTypes('string', 'null');
        $resolver->define('whitelist_parents')->default([])->allowedTypes('array');
        $resolver->define('group_whitelist_parents')->default([])->allowedTypes('array');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $whitelistParents = StringUtil::deserialize($model->whitelistParents);
        $groupWhitelistParents = StringUtil::deserialize($model->groupWhitelistParents);

        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('field_pid', $model->fieldPid ?: null)
            ->set('which_ptable', $model->whichPtable ?: null)
            ->set('whitelist_parents', $whitelistParents ? (array) $whitelistParents : [])
            ->set('group_whitelist_parents', \is_array($groupWhitelistParents) ? $groupWhitelistParents : []);
    }

    /**
     * @throws FilterException
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
        $config = $context->config;

        if (!$fieldPid = $config['field_pid'])
        {
            throw new FilterException('No parent field defined.');
        }

        $inferrable = PtableInferrableFactory::createFromConfig($context->list->config);
        $inferrer = new PtableInferrer($inferrable, $context->list->getDataContainerName());

        try
        {
            $ptable = $inferrer->getInferredPtable();
            $fieldDynamicPtable = $inferrer->tryGetDynamicPtableField();
        }
        catch (InferenceException)
        {
            $builder->abort();
        }

        if (\is_string($fieldDynamicPtable))
        {
            $builder->add(BelongsToRelationFilterType::class, [
                'field_pid' => $fieldPid,
                'field_dynamic_ptable' => $fieldDynamicPtable,
                'parent_groups' => $this->getDynamicParentGroups($config['group_whitelist_parents']),
            ]);

            return;
        }

        if (!$ptable || !$whitelistParents = $config['whitelist_parents']) {
            throw new FilterException('No whitelisted parents.');
        }

        $builder->add(BelongsToRelationFilterType::class, [
            'field_pid' => $fieldPid,
            'whitelist' => $whitelistParents,
        ]);
    }

    /**
     * Expected format:
     * ```php
     *   $submittedData = [
     *       'tl_article' => [1, 5, 35, ...],
     *       'tl_news' => [2, 3, 4, ...],
     *   ];
     * ```
     *
     * @param array $groupWhitelistParents Deserialized group whitelist, as stored in the
     *   `group_whitelist_parents` config key.
     */
    public function addDynamicPtableFilter(
        FilterBuilderInterface $builder,
        array                  $groupWhitelistParents,
        string                 $fieldDynamicPtable,
        string                 $fieldPid,
        ?array                 $submittedData = null,
    ): void {
        $builder->add(BelongsToRelationFilterType::class, [
            'field_pid' => $fieldPid,
            'field_dynamic_ptable' => $fieldDynamicPtable,
            'parent_groups' => $this->getDynamicParentGroups($groupWhitelistParents),
            'submitted_data' => $submittedData,
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

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $listModel = $context->listModel;
        $filterModel = $context->filterModel;

        if (!$filterModel)
        {
            Message::addError($this->trans->trans('errors.missing_model', [], 'flare'));
            $dca->palette('');
            return;
        }

        if (!$listModel->dc)
        {
            Message::addError($this->trans->trans('errors.missing_datacontainer', ['%id%' => $listModel->id], 'flare'));
            $dca->palette('');
            return;
        }

        $palette = '{filter_legend},fieldPid,whichPtable';

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

        if ($filterModel->whichPtable === 'dynamic')
        {
            $palette .= ';{archive_legend},groupWhitelistParents';
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        elseif ($ptable ?? null)
        {
            $palette .= ',whitelistParents';
        }

        $dca->palette($palette);
    }
}
