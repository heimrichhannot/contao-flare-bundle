<?php

namespace HeimrichHannot\FlareBundle\Util;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceConfigInterface;

/**
 * Class CallbackHelper
 *
 * @internal For internal use only. API might change without notice.
 */
class CallbackHelper
{
    /**
     * @throws \RuntimeException thrown if the callback method parameters cannot be auto-resolved
     *
     * @param ServiceConfigInterface[] $callbacks
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
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            if (isset($return)) {
                return $return;
            }
        }

        return null;
    }

    public static function tryGetProperty(object $obj, string $prop, mixed $default = null): mixed
    {
        if (\method_exists($obj, 'get' . \ucfirst($prop))) {
            return $obj->{'get' . \ucfirst($prop)}();
        }

        if (\property_exists($obj, $prop) || \method_exists($obj, '__get')) {
            return $obj->{$prop};
        }

        return $default;
    }
}