<?php

namespace HeimrichHannot\FlareBundle\Util;

class MethodInjector
{
    /**
     * @throws \ReflectionException
     */
    public static function invoke($service, string $method, array $availableParams = []): mixed
    {
        if (!method_exists($service, $method)) {
            throw new \InvalidArgumentException(sprintf(
                'Method %s::%s does not exist.',
                get_class($service),
                $method
            ));
        }

        $reflectionMethod = new \ReflectionMethod($service, $method);
        $arguments = [];

        foreach ($reflectionMethod->getParameters() as $parameter)
        {
            if ($parameter->getType() && !$parameter->getType()->isBuiltin())
            {
                $typeName = $parameter->getType()->getName();

                if (\array_key_exists($typeName, $availableParams))
                {
                    $arguments[] = $availableParams[$typeName];
                    continue;
                }
            }

            if (\array_key_exists($parameter->getName(), $availableParams))
            {
                $arguments[] = $availableParams[$parameter->getName()];
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