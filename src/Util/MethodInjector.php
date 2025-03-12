<?php

namespace HeimrichHannot\FlareBundle\Util;

/**
 * Class MethodInjector
 *
 * @internl For internal use only. API might change without notice.
 */
class MethodInjector
{
    /**
     * @throws \ReflectionException thrown by ReflectionMethod::invokeArgs()
     * @throws \InvalidArgumentException thrown if the method does not exist
     * @throws \RuntimeException thrown if a parameter cannot be resolved
     */
    public static function invoke(
        object $service,
        string $method,
        array $mandatoryParams = [],
        array $optionalParams = []
    ): mixed {
        if (!method_exists($service, $method)) {
            throw new \InvalidArgumentException(sprintf(
                'Method %s::%s does not exist.',
                get_class($service),
                $method
            ));
        }

        $reflectionMethod = new \ReflectionMethod($service, $method);
        $arguments = $mandatoryParams;

        $skipped = 0;

        foreach ($reflectionMethod->getParameters() as $parameter)
        {
            if ($skipped < \count($mandatoryParams))
            {
                $skipped++;
                continue;
            }

            if ($parameter->getType() && !$parameter->getType()->isBuiltin())
            {
                $typeName = $parameter->getType()->getName();

                if (\array_key_exists($typeName, $optionalParams))
                {
                    $arguments[] = $optionalParams[$typeName];
                    continue;
                }
            }

            if (\array_key_exists($parameter->getName(), $optionalParams))
            {
                $arguments[] = $optionalParams[$parameter->getName()];
                continue;
            }

            if ($parameter->isDefaultValueAvailable())
            {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if (!$parameter->hasType()
                || (($type = $parameter->getType()) instanceof \ReflectionNamedType
                    && ($type->allowsNull() || $type->getName() === 'mixed')))
            {
                $arguments[] = null;
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Unable to resolve parameter "%s" for method %s::%s',
                $parameter->getName(),
                get_class($service),
                $method
            ));
        }

        return $reflectionMethod->invokeArgs($service, $arguments);
    }
}