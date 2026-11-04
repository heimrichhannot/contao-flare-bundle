<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\Filter;

/**
 * One filter's node in the root filter form, as recorded by {@see Factory\FormHarnessFactory}.
 *
 * Only filters that actually mounted get an entry — filters skipped for an invalid alias, for
 * declaring no fields, having no control, or by a cancelled {@see \HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent}
 * are absent from {@see FormHarness::getMounts()}.
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
    /** Attribute-bag key marking a root form child as a flat-mounted single field. */
    public const ATTR_SINGLE_FIELD = 'flare.single_field';

    /**
     * @param Filter $filter The filter this mount belongs to.
     * @param string $alias Form name of the mounted node on the root form.
     * @param ControlContext $context Context the filter's control was built, hydrated and decoded with.
     */
    public function __construct(
        public Filter         $filter,
        public string         $alias,
        public ControlContext $context,
    ) {}
}
