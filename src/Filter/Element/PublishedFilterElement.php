<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\PublishedFilterType;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, intrinsicOnly: true)]
class PublishedFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_published';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('published_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('start_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('stop_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('invert')->default(false)->allowedTypes('bool');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $usePublished = (bool) ($model->usePublished ?? true);
        $useStart = (bool) ($model->useStart ?? true);
        $useStop = (bool) ($model->useStop ?? true);

        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('published_field', $usePublished ? ($model->fieldPublished ?: 'published') : null)
            ->set('start_field', $useStart ? ($model->fieldStart ?: 'start') : null)
            ->set('stop_field', $useStop ? ($model->fieldStop ?: 'stop') : null)
            ->set('invert', (bool) $model->invertPublished);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
        $config = $context->config;

        $builder->add(PublishedFilterType::class, [
            'published_field' => $config['published_field'],
            'start_field' => $config['start_field'],
            'stop_field' => $config['stop_field'],
            'invert_published' => $config['invert'],
            'now' => \time(),
        ]);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},usePublished,useStart,useStop');
    }
}
