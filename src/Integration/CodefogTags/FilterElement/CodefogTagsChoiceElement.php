<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry\CfgTagsJoinsRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

#[AsFilterElement(
    type: self::TYPE,
    palette: '{filter_legend},label,isMultiple,isExpanded,preselect',
    formType: ChoiceType::class,
    isTargeted: true,
)]
class CodefogTagsChoiceElement extends AbstractFilterElement implements IntrinsicValueContract
{
    public const TYPE = 'cfg_tags_choice';

    public function __construct(
        private readonly CfgTagsJoinsRegistry        $joinsRegistry,
        private readonly ListExecutionContextFactory $listExecutionContextFactory,
        private readonly LoggerInterface             $logger,
    ) {}

    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        /** @var ?array $tagIds */
        if (!$tagIds = $inv->getValue()) {
            return;
        }

        // todo: implement filter logic
    }

    #[AsFilterCallback(self::TYPE, 'config.onload')]
    public function onLoadConfig(FilterModel $filterModel): void
    {
        $table = FilterModel::getTable();
        $fields = &$GLOBALS['TL_DCA'][$table]['fields'];

        ###> isMultiple
        $field = &$fields['isMultiple'];
        $field['eval']['submitOnChange'] = true;
        ###< isMultiple

        ###> preselect
        $field = &$fields['preselect'];
        $field['inputType'] = 'select';
        $field['eval']['includeBlankOption'] = true;
        $field['eval']['multiple'] = $filterModel->isMultiple;
        $field['eval']['chosen'] = true;
        ###< preselect
    }

    private function normalizeValueArray(array $values): array
    {
        return \array_values(\array_unique(\array_filter(\array_map('\intval', $values))));
    }

    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): ?array
    {
        return $this->normalizeValueArray(
            StringUtil::deserialize($filter->preselect ?: null, true)
        ) ?: null;
    }

    public function processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): ?array
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

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
    {
        $list = $event->list;
        $filter = $event->filter;

        $emptyPlaceholder = $filter->isMandatory ? 'empty_option.prompt' : 'empty_option.no_selection';

        $options = $this->defaultFormTypeOptions($filter, [
            'multiple',
            'expanded',
            'required',
            'placeholder' => $emptyPlaceholder,
            'label' => null,
        ]);

        $event->options = \array_merge($event->options, $options);

        $context = $this->listExecutionContextFactory->create($list);

        if (\is_null($optValues = $this->getOptions($list, $filter, $context))) {
            return;
        }

        $choices = $event->choicesBuilder->enable();

        foreach ($optValues as $value => $label) {
            $choices->add((string) $value, (string) $label);
        }
    }

    #[AsFilterCallback(self::TYPE, 'fields.preselect.options')]
    public function getOptions(ListSpecification $list, FilterDefinition $filter, ListExecutionContext $context): ?array
    {
        $targetAlias = $filter->getTargetAlias();

        $activeTagsAliases = \array_intersect_key(
            $this->joinsRegistry->all(),
            \array_flip($context->tableAliasRegistry->getAliases()),
        );

        if (\count($activeTagsAliases) !== 1) {
            $this->logger->warning(\sprintf(
                '[FLARE] Cannot determine single target table for tags filter on '
                . 'list %s (ID %s), filter %s (ID %s), targetAlias %s',
                $list->type, (string) ($list->getDataSource()?->getListProperty('id') ?? 'N/A'),
                $filter->type, (string) ($filter->getDataSource()?->getFilterProperty('id') ?? 'N/A'),
                $targetAlias,
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