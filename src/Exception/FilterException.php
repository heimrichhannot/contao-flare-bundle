<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A filter cannot work with its config, its data or its value.
 *
 * This is the exception filter components throw, the bundled ones and userland ones alike: elements (in
 * buildContext() and the capability ports), controls (in buildForm(), decode() and hydrate()) and value providers.
 * The framework handles it per hook, from dropping a control to failing the list, and adds the filter's source; see
 * the hook's documentation. Report failures with this exception rather than with {@see FlareException}, which the
 * control hooks do not handle.
 *
 * @api
 */
class FilterException extends FlareException
{
}
