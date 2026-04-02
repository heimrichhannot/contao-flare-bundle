<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\RuntimeValueContract;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

final readonly class FilterValueResolver
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
    ) {}

    public function resolve(ListSpecification $spec, array $runtimeValues): array
    {
        $values = [];

        foreach ($spec->getFilters()->all() as $key => $filter)
        {
            $element = $this->filterElementRegistry->get($filter->getType())?->getService();

            if (\array_key_exists($key, $runtimeValues))
            {
                $value = $runtimeValues[$key];

                if ($element instanceof RuntimeValueContract) {
                    $value = $element->processRuntimeValue($value, $spec, $filter);
                }

                $values[$key] = $value;
                continue;
            }

            if ($element instanceof IntrinsicValueContract && $filter->isIntrinsic()) {
                $values[$key] = $element->getIntrinsicValue($spec, $filter);
            }
        }

        return $values;
    }
}