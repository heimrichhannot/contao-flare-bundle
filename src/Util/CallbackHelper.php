<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Util;

class CallbackHelper
{
    /**
     * Attempts to retrieve the value of a property from an object. If a getter method exists for the property,
     * it will be invoked. Otherwise, it will attempt to access the property directly or via magic methods.
     * If the property is not found or an error occurs, the default value is returned.
     *
     * @param object     $obj The object from which the property is retrieved.
     * @param string     $prop The name of the property to retrieve.
     * @param mixed      $default The default value to return if the property cannot be retrieved.
     *
     * @return mixed          The value of the property, if found; otherwise, the default value.
     */
    public static function tryGetProperty(object $obj, string $prop, mixed  $default = null): mixed
    {
        $getMethod = 'get' . \ucfirst($prop);

        if (\method_exists($obj, $getMethod))
        {
            try
            {
                return $obj->{$getMethod}();
            }
            /** @mago-expect lint:no-empty-catch-clause This is intentional. */
            catch (\ArgumentCountError)
            {
                // Skip to direct property access if the getter method expects arguments.
            }
            catch (\Throwable)
            {
                return $default;
            }
        }

        if (\property_exists($obj, $prop) || \method_exists($obj, '__get'))
        {
            return $obj->{$prop};
        }

        return $default;
    }
}
