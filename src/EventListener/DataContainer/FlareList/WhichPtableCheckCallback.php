<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareList;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;

#[AsCallback(ListContainer::TABLE_NAME, 'config.onload')]
#[AsCallback(ListContainer::TABLE_NAME, 'config.onsubmit')]
readonly class WhichPtableCheckCallback
{
    public function __invoke(?DataContainer $dc): void
    {
        if (!$dc?->id) {
            return;
        }

        // ignore type because the type is not updated yet
        if (!$listModel = ListModel::findByPk($dc->id)) {
            return;
        }

        try
        {
            $inferrer = new PtableInferrer($listModel, $listModel);

            $inferrer->infer();

            if (!$inferrer->isAutoInferable())
            {
                $listModel->whichPtable_disableAutoOption();
            }
        }
        catch (InferenceException) {}
    }
}