<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Model;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Model\ListModel;

class ReaderManager
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FilterContextManager $filterContextManager,
        private readonly FilterListManager $filterListManager,
    ) {}

    public function getModel(ListModel $listModel, string|int $autoItem): ?Model
    {
        if (!$filterContextCollection = $this->filterContextManager->collect($listModel)) {
            return null;
        }



        return null;
    }
}