<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Model;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\SqlEquationOperator;

readonly class ReaderManager
{
    public function __construct(
        private FilterContextManager    $filterContextManager,
        private ListItemProviderManager $itemProvider,
        private FilterElementRegistry   $filterElementRegistry,
    ) {}

    /**
     * @throws FlareException
     */
    public function getModelByAutoItem(
        string|int     $autoItem,
        ListModel      $listModel,
        ContentContext $contentContext,
    ): ?Model {
        if (!($table = $listModel->dc)
            || !($collection = $this->filterContextManager->collect($listModel, $contentContext)))
        {
            return null;
        }

        $modelClass = Model::getClassFromTable($table);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), source: __METHOD__);
        }

        $autoItemDefinition = SimpleEquationElement::define(
            equationLeft: $listModel->getAutoItemField(),
            equationOperator: SqlEquationOperator::EQUALS,
            equationRight: $autoItem,
        )->setAlias('_flare_auto_item', $ogAlias);

        $autoItemFilterContext = $this->filterContextManager->definitionToContext(
            definition: $autoItemDefinition,
            listModel: $listModel,
            contentContext: $contentContext,
            config: $this->filterElementRegistry->get($ogAlias),
        );

        if (!$autoItemFilterContext) {
            return null;
        }

        $collection->add($autoItemFilterContext);

        $itemProvider = $this->itemProvider->ofListModel($listModel);

        try
        {
            $ids = $itemProvider->fetchIds(filters: $collection);
        }
        catch (\Exception $e)
        {
            throw new FlareException('Error fetching entries for auto_item.', previous: $e, source: __METHOD__);
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