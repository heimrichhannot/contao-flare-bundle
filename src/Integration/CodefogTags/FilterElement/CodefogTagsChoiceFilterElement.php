<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\IntegerIdChoiceFilterType;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry\CfgTagsJoinsRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class CodefogTagsChoiceFilterElement extends AbstractFilterElement
{
    public const TYPE = 'cfg_tags_choice';

    public function __construct(
        private readonly ChoicesBuilderFactory       $choicesBuilderFactory,
        private readonly CfgTagsJoinsRegistry        $joinsRegistry,
        private readonly ListExecutionContextFactory $listExecutionContextFactory,
        private readonly LoggerInterface             $logger,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('preselect')->default([])->allowedTypes('int[]');
        $resolver->define('is_mandatory')->default(false)->allowedTypes('bool');
        $resolver->define('is_multiple')->default(false)->allowedTypes('bool');
        $resolver->define('is_expanded')->default(false)->allowedTypes('bool');
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
        $resolver->define('placeholder')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('preselect', $this->normalizeValueArray(
                StringUtil::deserialize($model->preselect ?: null, true)
            ))
            ->set('is_mandatory', (bool) $model->isMandatory)
            ->set('is_multiple', (bool) $model->isMultiple)
            ->set('is_expanded', (bool) $model->isExpanded)
            ->set('label', $model->label ?: null)
            ->set('placeholder', $model->placeholder ?: null);
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        $config = $context->config;

        if ($config['intrinsic']) {
            return;
        }

        $placeholderFallback = $config['is_mandatory'] ? 'empty_option.prompt' : 'empty_option.no_selection';

        $formOptions = [
            'label' => $config['label'] ?: false,
            'multiple' => $config['is_multiple'],
            'expanded' => $config['is_expanded'],
            'required' => $config['is_mandatory'],
            'placeholder' => $config['placeholder'] ?: $placeholderFallback,
        ];

        if ($preselect = $config['preselect']) {
            $formOptions['data'] = $config['is_multiple'] ? $preselect : \reset($preselect);
        }

        $executionContext = $this->listExecutionContextFactory->create($context->list);

        $optValues = $this->getOptions(
            executionContext: $executionContext,
            targetAlias: $context->filter->targetAlias,
            listInfo: \sprintf(
                '%s (%s)',
                $context->list->getTypeAlias() ?? 'inline',
                (string) ($context->list->source ?? 'N/A'),
            ),
            filterInfo: \sprintf('%s (%s)', self::TYPE, $context->filter->source ?? 'inlined'),
        );

        if (!\is_null($optValues))
        {
            $choicesBuilder = $this->choicesBuilderFactory->createChoicesBuilder()->enable();

            foreach ($optValues as $value => $label) {
                $choicesBuilder->add((string) $value, (string) $label, (int) $value);
            }

            $formOptions['choice_loader'] = $choicesBuilder->buildCallbackChoiceLoader();
            $formOptions['choice_label'] = $choicesBuilder->buildChoiceLabelCallback();
            $formOptions['choice_value'] = $choicesBuilder->buildChoiceValueCallback();

            $builder->setAttribute('flare.choices_builder', $choicesBuilder);
        }

        $builder->add(FilterContext::FIELD_VALUE, ChoiceType::class, $formOptions);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        $config = $context->config;

        $preselect = $config['preselect'] ?: null;

        /** @var ?array $tagIds */
        $tagIds = $config['intrinsic']
            ? $preselect
            : $this->processRuntimeValue($data[FilterContext::FIELD_VALUE] ?? null);

        if (!$tagIds) {
            return;
        }

        $builder->add(IntegerIdChoiceFilterType::class, [
            'field' => 'id',
            'ids' => $tagIds,
        ]);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{form_legend},label,isMandatory,isMultiple,isExpanded;{filter_legend},preselect');

        $dca->field('isMultiple')
            ->eval(['submitOnChange' => true]);

        $dca->field('preselect')
            ->inputType('select')
            ->eval([
                'includeBlankOption' => true,
                'multiple' => (bool) $context->filterModel?->isMultiple,
                'chosen' => true,
            ])
            ->options(function () use ($context): array {
                if (!$executionContext = $context->getExecutionContext()) {
                    return [];
                }

                return $this->getOptions(
                    executionContext: $executionContext,
                    targetAlias: (string) ($context->filterModel->targetAlias ?? ''),
                    listInfo: \sprintf('%s (ID %s)', $context->listModel->type, $context->listModel->id),
                    filterInfo: \sprintf('%s (ID %s)', $context->type, (string) ($context->filterModel->id ?? 'N/A')),
                ) ?? [];
            });
    }

    private function normalizeValueArray(array $values): array
    {
        return \array_values(\array_unique(\array_filter(\array_map('\intval', $values))));
    }

    public function processRuntimeValue(mixed $value): ?array
    {
        if (!$value = StringUtil::deserialize($value)) {
            return null;
        }

        if (\is_numeric($value)) {
            $value = (int) $value;
            return $value > 0 ? [$value] : null;
        }

        if (\is_array($value)) {
            return $this->normalizeValueArray($value);
        }

        return null;
    }

    /**
     * Builds the tag options of the single active Codefog tags relation. Doubles as the
     * backend options provider for the preselect field and the runtime choices source.
     */
    public function getOptions(
        ListExecutionContext $executionContext,
        ?string              $targetAlias,
        string               $listInfo = 'N/A',
        string               $filterInfo = 'N/A',
    ): ?array {
        $activeTagsAliases = \array_intersect_key(
            $this->joinsRegistry->all(),
            \array_flip($executionContext->tableAliasRegistry->getAliases()),
        );

        if (\count($activeTagsAliases) !== 1) {
            $this->logger->warning(\sprintf(
                '[FLARE] Cannot determine single target table for tags filter on '
                . 'list %s, filter %s, targetAlias %s',
                $listInfo, $filterInfo, $targetAlias,
            ));
            return null;
        }

        $tableAlias = \array_key_first($activeTagsAliases);
        $config = $this->joinsRegistry->get($tableAlias);

        $options = [];

        /** @var \Codefog\TagsBundle\Tag $tag */
        foreach ($config?->manager->getAllTags() ?? [] as $tag) {
            $value = $tag->getValue();
            $options[$value] = "{$tag->getName()} [{$value}]";
        }

        return $options;
    }
}
