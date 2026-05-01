<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\Type\PublishedFilterType;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;

#[AsFilterElement(
    type: self::TYPE,
    palette: '{filter_legend},usePublished,useStart,useStop'
)]
class PublishedElement extends AbstractFilterElement
{
    public const TYPE = 'flare_published';

    public function buildFilter(FilterBuilderInterface $builder, FilterInvocation $invocation): void
    {
        $filter = $invocation->filter;

        $builder->add(PublishedFilterType::class, [
            'published_field' => ($filter->usePublished ?? true) ? ($filter->fieldPublished ?: 'published') : null,
            'start_field' => ($filter->useStart ?? true) ? ($filter->fieldStart ?: 'start') : null,
            'stop_field' => ($filter->useStop ?? true) ? ($filter->fieldStop ?: 'stop') : null,
            'invert_published' => (bool) ($filter->invertPublished ?? false),
            'now' => \time(),
        ]);
    }

    public static function define(
        string|false|null $published = null,
        string|false|null $start = null,
        string|false|null $stop = null,
        bool|null $invertPublished = null,
    ): ConfiguredFilter {
        $published ??= 'published';
        $start ??= 'start';
        $stop ??= 'stop';
        $invertPublished ??= false;

        $definition = new ConfiguredFilter(
            type: static::TYPE,
            intrinsic: true,
        );

        if ($published) {
            $definition->usePublished = true;
            $definition->fieldPublished = $published;
            $definition->invertPublished = $invertPublished;
        }

        if ($start) {
            $definition->useStart = true;
            $definition->fieldStart = $start;
        }

        if ($stop) {
            $definition->useStop = true;
            $definition->fieldStop = $stop;
        }

        return $definition;
    }
}
