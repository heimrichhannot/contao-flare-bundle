<?php

namespace HeimrichHannot\FlareBundle\Util;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceDescriptorInterface;

/**
 * Class CallbackHelper
 *
 * @internal For internal use only. API might change without notice.
 */
class CallbackHelper
{
    /**
     * Invokes a set of callbacks with the given mandatory and optional parameters.
     *
     * @param ServiceDescriptorInterface[] $callbacks An array of callbacks or a single callable.
     * @param array                        $mandatory Mandatory parameters to pass to the callbacks.
     * @param array                        $parameters Optional parameters to pass to the callbacks.
     *
     * @throws \InvalidArgumentException if the callback is not callable.
     * @throws \RuntimeException if an error occurs while invoking the callback.
     */
    public static function call(array $callbacks, array $mandatory, array $parameters): void
    {
        foreach ($callbacks as $callbackConfig)
        {
            $method = $callbackConfig?->getMethod();
            $service = $callbackConfig?->getService();

            if (!$method || !$service || !\method_exists($service, $method)) {
                continue;
            }

            try
            {
                MethodInjector::invoke($service, $method, $mandatory, $parameters);
            }
            catch (\Exception $e)
            {
                throw new \RuntimeException(
                    \sprintf('Error invoking callback: %s', $e->getMessage()), $e->getCode(), $e
                );
            }
        }
    }

    /**
     * @param ServiceDescriptorInterface[] $callbacks
     * @throws \RuntimeException thrown if the callback method parameters cannot be auto-resolved
     */
    public static function firstReturn(array $callbacks, array $mandatory, array $parameters): mixed
    {
        foreach ($callbacks as $callbackConfig)
        {
            $method = $callbackConfig?->getMethod();
            $service = $callbackConfig?->getService();

            if (!$method || !$service || !\method_exists($service, $method)) {
                continue;
            }

            try {
                $return = MethodInjector::invoke($service, $method, $mandatory, $parameters);
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    \sprintf('Error invoking callback: %s', $e->getMessage()), $e->getCode(), $e
                );
            }

            if (isset($return)) {
                return $return;
            }
        }

        return null;
    }

    public static function tryGetProperty(object $obj, string $prop, mixed $default = null): mixed
    {
        try {
            if (\method_exists($obj, 'get' . \ucfirst($prop))) {
                return $obj->{'get' . \ucfirst($prop)}();
            }
        } catch (\Throwable) {
            // Ignore exceptions from getter methods
        }

        if (\property_exists($obj, $prop) || \method_exists($obj, '__get')) {
            return $obj->{$prop};
        }

        return $default;
    }
}