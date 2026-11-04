<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer;

use HeimrichHannot\FlareBundle\Contao\Factory\FilterCompoundFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Model\FilterModel;

/**
 * Creates the element context of the filter edited in the backend.
 *
 * @internal
 */
final readonly class BackendElementContextFactory
{
    public function __construct(
        private FilterCompoundFactory $filterCompoundFactory,
    ) {}

    /**
     * The backend context knows the control, which decides the element's options, but resolves no value, so an
     * unreadable value cannot block the form that repairs it. Null if the list or the filter cannot be built.
     *
     * @param \Closure(): ListCompound $getList
     *
     * @mago-expect lint:no-empty-catch-clause Backend configuration must not fail on broken list configs.
     */
    public function create(\Closure $getList, FilterModel $filterModel): ?ElementContext
    {
        try
        {
            return new ElementContext(
                list: $getList(),
                filter: $this->filterCompoundFactory->create($filterModel, withValue: false),
                intent: new ConfigurationIntent(),
            );
        }
        catch (\Exception) {}

        return null;
    }
}
