<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use Contao\Model;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class ReaderRequestAttribute
{
    public function __construct(
        public Model     $displayModel,
        public ListModel $listModel,
    ) {}

    public function marshal(): array
    {
        return [
            'model_class' => $this->displayModel::class,
            'model_table' => $this->displayModel::getTable(),
            'model_id' => $this->displayModel->id,
            'list_id' => $this->listModel->id,
        ];
    }
}
