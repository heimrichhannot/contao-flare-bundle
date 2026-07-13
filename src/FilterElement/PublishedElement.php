<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ConfigContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\PublishedFilterType;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, intrinsicOnly: true)]
class PublishedElement extends AbstractFilterElement implements ConfigContract, DcaContract
{
    public const TYPE = 'flare_published';

    public function configureConfig(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('published_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('start_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('stop_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('invert')->default(false)->allowedTypes('bool');
    }

    public function configFromRow(array $row): array
    {
        $usePublished = $row['usePublished'] ?? true;
        $useStart = $row['useStart'] ?? true;
        $useStop = $row['useStop'] ?? true;

        return [
            'intrinsic' => (bool) ($row['intrinsic'] ?? false),
            'published_field' => $usePublished ? (($row['fieldPublished'] ?? null) ?: 'published') : null,
            'start_field' => $useStart ? (($row['fieldStart'] ?? null) ?: 'start') : null,
            'stop_field' => $useStop ? (($row['fieldStop'] ?? null) ?: 'stop') : null,
            'invert' => (bool) ($row['invertPublished'] ?? false),
        ];
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
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

    public function configureDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},usePublished,useStart,useStop');
    }

    public static function define(
        string|false|null $published = null,
        string|false|null $start = null,
        string|false|null $stop = null,
        bool|null $invertPublished = null,
    ): Filter {
        $published ??= 'published';
        $start ??= 'start';
        $stop ??= 'stop';
        $invertPublished ??= false;

        return new Filter(
            element: static::TYPE,
            config: [
                'intrinsic' => true,
                'published_field' => $published ?: null,
                'start_field' => $start ?: null,
                'stop_field' => $stop ?: null,
                'invert' => $published ? $invertPublished : false,
            ],
        );
    }
}
