<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

use HeimrichHannot\FlareBundle\Filter\Filter;

/**
 * A filter cannot work with its config, its data or its value.
 *
 * This is the exception filter components throw, the bundled ones and userland ones alike: elements (in
 * buildContext() and the capability ports), controls (in buildForm(), decode() and hydrate()) and value providers.
 * The framework handles it per hook, from dropping a value to failing the list, and adds the filter's source; see
 * the hook's documentation. Any other exception from a hook is handled the same way but treated as unexpected:
 * logged as an error, and rethrown in debug mode. {@see FlareException} is reserved for the framework itself.
 *
 * @api
 */
class FilterException extends FlareException
{
    /**
     * The failure of a filter hook as a filter exception: a FilterException is kept and only gets the filter's
     * source and the hook's method where it names none; any other exception is wrapped. Errors (`\Error`) are
     * programming errors and are never passed here.
     *
     * @param string $method The failing hook, e.g. `SomeControl::buildForm`.
     */
    public static function wrap(\Exception $e, Filter $filter, string $method): self
    {
        $source = $filter->source ?: 'filter inlined';

        if (!$e instanceof self) {
            return new self($e->getMessage(), $e->getCode(), $e, method: $method, source: $source);
        }

        if ($e->getSource() === null) {
            $e->setSource($source);
        }

        if ($e->getMethod() === null) {
            $e->setMethod($method);
        }

        return $e;
    }
}
