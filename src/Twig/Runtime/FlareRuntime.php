<?php

namespace HeimrichHannot\FlareBundle\Twig\Runtime;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Manager\FilterListManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;

class FlareRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly FilterListManager $filterListManager,
    ) {}

    /**
     * @throws FilterException
     */
    public function getFormComponent(ListModel|string|int $listModel, ?string $formName = null): FormInterface
    {
        $listModel = $this->getListModel($listModel);
        $formName = $this->filterListManager->makeFormName($listModel, $formName);

        return $this->filterListManager->getForm($listModel, $formName);
    }

    /**
     * @throws FilterException
     */
    public function getFormView(ListModel|string|int $listModel, ?string $formName = null): FormView
    {
        return $this->getFormComponent($listModel, $formName)->createView();
    }

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     *
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(ListModel|string|int $listModel, ?string $formName = null): array
    {
        $listModel = $this->getListModel($listModel);
        $formName = $this->filterListManager->makeFormName($listModel, $formName);

        return $this->filterListManager->getEntries($listModel, $formName);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getListModel(ListModel|string|int $listModel): ?ListModel
    {
        if ($listModel instanceof ListModel) {
            return $listModel;
        }

        $listModel = ListModel::findByPk(\intval($listModel));

        if ($listModel instanceof ListModel) {
            return $listModel;
        }

        throw new \InvalidArgumentException('Invalid list model');
    }
}