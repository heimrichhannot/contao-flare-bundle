<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

/**
 * One filter's node in the root filter form, as recorded by {@see Factory\FilterSetFactory}.
 *
 * Only filters that actually mounted get an entry — filters skipped for an invalid alias, for
 * declaring no fields, or by a cancelled {@see \HeimrichHannot\FlareBundle\Event\FilterFormBuiltEvent}
 * are absent from {@see FilterSet::getMounts()}.
 *
 * {@see $filter} is deliberately redundant with `$context->filter`: it is the field consumers
 * reach for, and going through the context would be a hop through an unrelated concern. Only
 * {@see $alias} carries information the context does not — the non-empty, valid-form-name
 * invariant that {@see \HeimrichHannot\FlareBundle\Util\Str::isValidFormName()} established
 * before the filter was mounted.
 *
 * @api
 */
final readonly class FilterMount
{
    /**
     * @param Filter $filter The filter this mount belongs to.
     * @param string $alias Form name of the mounted node on the root form.
     * @param FilterContext $context Invocation context the filter's form was built with.
     */
    public function __construct(
        public Filter        $filter,
        public string        $alias,
        public FilterContext $context,
    ) {}
}
