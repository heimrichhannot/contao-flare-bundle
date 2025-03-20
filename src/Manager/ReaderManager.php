<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Builder\FilterContextBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquation;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\DBEquationOperator;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

readonly class ReaderManager
{
    public function __construct(
        private FilterContextBuilderFactory $filterContextBuilderFactory,
        private FilterContextManager        $filterContextManager,
        private SimpleEquation              $simpleEquation,
    ) {}

    /**
     * @throws FlareException
     */
    public function getModel(ListModel $listModel, string|int $autoItem): ?Model
    {
        if (!($table = $listModel->dc)
            || !($collection = $this->filterContextManager->collect($listModel)))
        {
            return null;
        }

        $modelClass = Model::getClassFromTable($table);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), source: __METHOD__);
        }

        $fieldAutoItem = $listModel->fieldAutoItem ?: DcaHelper::tryGetColumnName($table, 'alias', 'id');

        $context = $this->filterContextBuilderFactory->create()
            ->setListModel($listModel)
            ->setFilterElement($this->simpleEquation)
            ->setFilterElementAlias('_flare_auto_item')
            ->setFilterModelProperties([
                'equationLeft' => $fieldAutoItem,
                'equationOperator' => DBEquationOperator::EQUALS->value,
                'equationRight' => $autoItem,
            ])
            ->build();

        if (!$context) {
            return null;
        }

        $collection->add($context);

        try
        {
            $ids = $this->filterContextManager->fetchEntries($collection, returnIds: true);
        }
        catch (\Exception $e)
        {
            throw new FlareException('Error fetching entries for auto_item.', source: __METHOD__, previous: $e);
        }

        if (empty($ids)) {
            return null;
        }

        if (\count($ids) > 1) {
            throw new FlareException('Multiple entries found for auto_item.', source: __METHOD__);
        }

        $id = \intval(\reset($ids));

        if ($id < 1) {
            throw new FlareException('Invalid entry id.', source: __METHOD__);
        }

        return $modelClass::findByPk($id);
    }
}