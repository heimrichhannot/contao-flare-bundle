<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer;

use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Contao\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;

/**
 * Creates the element context of the filter edited in the backend.
 *
 * @internal
 */
final readonly class BackendElementContextFactory
{
    public function __construct(
        private FilterFactory $filterFactory,
    ) {}

    /**
     * The backend context knows the control, which decides the element's options, but resolves no value, so an
     * unreadable value cannot block the form that repairs it. Null if the list or the filter cannot be built.
     *
     * @param \Closure(): ListSpec $getSpec
     *
     * @mago-expect lint:no-empty-catch-clause Backend configuration must not fail on broken list configs.
     */
    public function create(\Closure $getSpec, FilterModel $filterModel): ?ElementContext
    {
        try
        {
            return new ElementContext(
                list: $getSpec(),
                filter: $this->filterFactory->create($filterModel, withValue: false),
                intent: new ConfigurationIntent(),
            );
        }
        catch (\Exception) {}

        return null;
    }
}
