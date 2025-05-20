<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;

#[AsCallback(FilterContainer::TABLE_NAME, 'config.onsubmit')]
readonly class ConfigOnSubmitCallback
{
    public function __construct(
        private FilterContainer $filterContainer,
    ) {}

    public function __invoke(DataContainer $dc): void
    {
        // ignore type because the type is not updated yet
        [$filterModel, $listModel] = $this->filterContainer->getModelsFromDataContainer($dc, ignoreType: true);

        try
        {
            $inferrer = new PtableInferrer($filterModel, $listModel);

            $inferrer->infer();

            if (!$inferrer->isAutoInferable())
            {
                $filterModel->whichPtable_disableAutoOption();
            }
        }
        catch (InferenceException) {}
    }
}