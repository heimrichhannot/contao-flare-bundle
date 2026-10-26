<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Predicate\PublishedPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE)]
class PublishedFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_published';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('published_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('start_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('stop_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('invert')->default(false)->allowedTypes('bool');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $usePublished = (bool) ($model->elm_usePublished ?? true);
        $useStart = (bool) ($model->elm_useStart ?? true);
        $useStop = (bool) ($model->elm_useStop ?? true);
        $fieldPublished = $model->elm_fieldPublished ?: 'published';
        $fieldStart = $model->elm_fieldStart ?: 'start';
        $fieldStop = $model->elm_fieldStop ?: 'stop';

        $config
            ->set('published_field', $usePublished ? $fieldPublished : null)
            ->set('start_field', $useStart ? $fieldStart : null)
            ->set('stop_field', $useStop ? $fieldStop : null)
            ->set('invert', (bool) $model->elm_invertPublished);
    }

    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
        $config = $builder->scope->filter->config;

        $builder->addPredicate(PublishedPredicate::class, [
            'published_field' => $config['published_field'],
            'start_field' => $config['start_field'],
            'stop_field' => $config['stop_field'],
            'invert_published' => $config['invert'],
            'now' => \time(),
        ]);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{filter_legend},elm_usePublished,elm_useStart,elm_useStop');
    }
}
