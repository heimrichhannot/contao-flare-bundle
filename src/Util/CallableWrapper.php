<?php

namespace HeimrichHannot\FlareBundle\Util;

/**
 * @see \Contao\CoreBundle\Twig\Interop\ContextFactory::getCallableWrapper()
 */
final readonly class CallableWrapper implements \Stringable
{
    private \Closure $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Delegates call to callable, e.g., when in a Contao template context.
     */
    public function __invoke(mixed ...$args): mixed
    {
        return ($this->callable)(...$args);
    }

    /**
     * Called when evaluating "{{ var }}" in a Twig template.
     */
    public function __toString(): string
    {
        return (string) $this();
    }

    /**
     * Called when evaluating "{{ var.invoke() }}" in a Twig template. We do not cast
     * to string here, so that other types (like arrays) are supported as well.
     */
    public function invoke(mixed ...$args): mixed
    {
        return $this(...$args);
    }
}