<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\SearchKeywordsPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true, value: KeywordsValue::class)]
class SearchKeywordsFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_search_keywords';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('columns')->default([])->allowedTypes('array');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('columns', StringUtil::deserialize($model->elm_columnsGeneric, true));
    }

    /**
     * @throws FilterException
     */
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        if (!$value = $this->expectValue($value, KeywordsValue::class)) {
            return;
        }

        // A phrase that cannot be searched must not widen the list.
        if (!$columns = $scope->filter->element->config->all()['columns'] ?? []) {
            $builder->abort();
        }

        $builder->addPredicate(SearchKeywordsPredicate::class, [
            'value' => $value->keywords,
            'columns' => $columns,
        ]);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{filter_legend},elm_columnsGeneric');
    }
}
