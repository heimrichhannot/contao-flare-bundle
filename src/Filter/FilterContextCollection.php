<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Collection\Collection;

/**
 * A type-safe collection specifically for FilterContext objects.
 *
 * @extends Collection<FilterContext>
 */
class FilterContextCollection extends Collection
{
    /** {@inheritDoc} */
    protected function getItemType(): string
    {
        return FilterContext::class;
    }

    public function collectFormTypes(): array
    {
        $formTypes = [];

        foreach ($this->items as $filter)
        {
            if ($formType = $filter->getConfig()->getFormType())
            {
                $formTypes[] = $formType;
            }
        }

        return $formTypes;
    }
}