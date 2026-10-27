<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Filter;

/**
 * One filter's node in the root filter form, as recorded by {@see Factory\FormHarnessFactory}.
 *
 * Only filters that actually mounted get an entry — filters skipped for an invalid alias, for
 * declaring no fields, having no control, or by a cancelled {@see \HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent}
 * are absent from {@see FormHarness::getMounts()}.
 *
 * {@see $filter} is deliberately redundant with `$scope->filter`: it is the field consumers
 * reach for, and going through the scope would be a hop through an unrelated concern. Only
 * {@see $alias} carries information the scope does not — the non-empty, valid-form-name
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
     * @param ControlScope $scope Scope the filter's control was built, hydrated and decoded with.
     */
    public function __construct(
        public Filter      $filter,
        public string      $alias,
        public ControlScope $scope,
    ) {}
}
