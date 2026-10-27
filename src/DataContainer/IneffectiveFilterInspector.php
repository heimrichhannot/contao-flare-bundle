<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer;

use HeimrichHannot\FlareBundle\Contract\FilterElement\IneffectiveFilterContract;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Asks the element of a stored filter whether it restricts anything as configured
 * ({@see IneffectiveFilterContract}), for the backend's filter list and edit form.
 *
 * @internal
 */
final readonly class IneffectiveFilterInspector
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private FilterFactory         $filterFactory,
        private TranslatorInterface   $translator,
    ) {}

    /**
     * The translated reason why the filter restricts nothing, or null if it restricts, its element does not tell, or
     * the filter cannot be built (the backend reports that elsewhere).
     *
     * @param FilterModel|array<string, mixed> $filter The filter model, or its row.
     */
    public function describe(FilterModel|array $filter): ?string
    {
        $type = \is_array($filter) ? (string) ($filter['type'] ?? '') : (string) $filter->type;

        // Checked first, so rows of other element types are never loaded or built.
        if (!$this->filterElementRegistry->getService($type) instanceof IneffectiveFilterContract) {
            return null;
        }

        try
        {
            $model = \is_array($filter) ? FilterModel::findByPk($filter['id'] ?? 0) : $filter;

            if ($model === null) {
                return null;
            }

            $built = $this->filterFactory->createFromFilterModel($model);
        }
        catch (\Exception)
        {
            return null;
        }

        $element = $built->element->component;

        if (!$element instanceof IneffectiveFilterContract) {
            return null;
        }

        return $element->getIneffectiveReason($built)?->trans($this->translator);
    }

    /**
     * How many of the given filters restrict nothing ({@see describe()}).
     *
     * @param iterable<FilterModel|array<string, mixed>> $filters Filter models, or their rows.
     */
    public function countIneffective(iterable $filters): int
    {
        $count = 0;

        foreach ($filters as $filter)
        {
            if ($this->describe($filter) !== null) {
                ++$count;
            }
        }

        return $count;
    }
}
