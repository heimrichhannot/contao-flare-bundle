<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A non-empty stored filter value yielded no readable content. Fail closed (SPEC_FILTER_FORMS.md
 * §8.1): the value must not silently degrade to "no value", which would leave the list unfiltered.
 *
 * Thrown by value providers, userland ones included
 * ({@see \HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface::provide()}).
 *
 * @api
 */
class UnreadableValueException extends FilterException
{
}
