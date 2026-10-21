<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Predicate\IntegerIdChoicePredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry\CfgTagsJoinsRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true, value: ChoiceValue::class)]
class CodefogTagsChoiceFilterElement extends AbstractFilterElement implements ChoiceSourceContract
{
    public const TYPE = 'cfg_tags_choice';

    public function __construct(
        private readonly CfgTagsJoinsRegistry        $joinsRegistry,
        private readonly ListExecutionContextFactory $listExecutionContextFactory,
        private readonly LoggerInterface             $logger,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void {}

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    /**
     * @throws FilterException
     */
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
        if (!$value = $this->expectValue($value, ChoiceValue::class)) {
            return;
        }

        $tagIds = [];

        foreach ($value->choices as $choice)
        {
            if ($choice instanceof ValueChoice && ($id = (int) $choice->value) > 0) {
                $tagIds[] = $id;
            }
        }

        if (!$tagIds) {
            $builder->abort();
        }

        $builder->addPredicate(IntegerIdChoicePredicate::class, [
            'field' => 'id',
            'ids' => $tagIds,
        ]);
    }

    /**
     * @throws FilterException
     */
    public function createChoices(FilterScope $scope): ChoicesBuilder
    {
        try
        {
            $executionContext = $this->listExecutionContextFactory->create($scope->list);
        }
        catch (FlareException $e)
        {
            throw new FilterException($e->getMessage(), previous: $e, method: __METHOD__);
        }

        $options = $this->getOptions(
            executionContext: $executionContext,
            targetAlias: $scope->filter->targetAlias,
            listInfo: \sprintf('%s (%s)', $scope->list->driver::class, $scope->list->source ?? 'N/A'),
            filterInfo: \sprintf('%s (%s)', self::TYPE, $scope->filter->source ?? 'inlined'),
        );

        if (\is_null($options)) {
            throw new FilterException(
                'Cannot determine a single target table for the tags filter.',
                method: __METHOD__,
            );
        }

        $choices = $this->createChoicesBuilder();

        foreach ($options as $tagId => $label) {
            $choices->add(new ValueChoice((string) $tagId, (string) $label));
        }

        return $choices;
    }

    /**
     * Tag ids are stored as bare keys; anything but a positive integer without leading zeros is unreadable.
     */
    public function choiceFromKey(string $key): ?ValueChoice
    {
        return \preg_match('/^[1-9]\d*\z/', $key) ? new ValueChoice($key) : null;
    }

    /**
     * Builds the tag options of the single active Codefog tags relation.
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
