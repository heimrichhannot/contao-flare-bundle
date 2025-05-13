<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Builder\FilterContextBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\SqlEquationOperator;

readonly class ReaderManager
{
    public function __construct(
        private FilterContextBuilderFactory $contextBuilderFactory,
        private FilterContextManager        $contextManager,
        private FilterQueryManager          $queryManager,
        private SimpleEquationElement       $simpleEquation,
    ) {}

    /**
     * @throws FlareException
     */
    public function getModelByAutoItem(ListModel $listModel, string|int $autoItem): ?Model
    {
        if (!($table = $listModel->dc)
            || !($collection = $this->contextManager->collect($listModel)))
        {
            return null;
        }

        $modelClass = Model::getClassFromTable($table);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), source: __METHOD__);
        }

        $context = $this->contextBuilderFactory->create()
            ->setListModel($listModel)
            ->setFilterElement($this->simpleEquation)
            ->setFilterElementAlias('_flare_auto_item')
            ->setFilterModelProperties([
                'equationLeft' => $listModel->getAutoItemField(),
                'equationOperator' => SqlEquationOperator::EQUALS->value,
                'equationRight' => $autoItem,
            ])
            ->build();

        if (!$context) {
            return null;
        }

        $collection->add($context);

        try
        {
            $ids = $this->queryManager->fetchEntries(filters: $collection, returnIds: true);
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