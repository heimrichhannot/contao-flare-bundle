<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;

#[AsCallback(FilterContainer::TABLE_NAME, 'config.onload')]
#[AsCallback(FilterContainer::TABLE_NAME, 'config.onsubmit')]
readonly class WhichPtableCheckCallback
{
    public function __construct(
        private FilterContainer $filterContainer,
    ) {}

    public function __invoke(?DataContainer $dc): void
    {
        if (!$dc) {
            return;
        }

        // ignore the type because it is not updated yet
        [$filterModel, $listModel] = $this->filterContainer->getModelsFromDataContainer($dc, ignoreType: true);

        if (!$filterModel || !$listModel) {
            return;
        }

        try
        {
            $inferrer = new PtableInferrer($filterModel, $listModel->dc);

            $inferrer->infer();

            if (!$inferrer->isAutoInferable())
            {
                $filterModel->whichPtable_disableAutoOption();
            }
        }
        /** @mago-expect lint:no-empty-catch-clause It's fine to skip if an inference exception occurs.' */
        catch (InferenceException) {}
    }
}