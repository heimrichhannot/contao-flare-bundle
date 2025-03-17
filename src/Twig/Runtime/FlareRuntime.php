<?php

namespace HeimrichHannot\FlareBundle\Twig\Runtime;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\ListView\Builder\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;

class FlareRuntime implements RuntimeExtensionInterface
{
    protected array $containerCache = [];

    public function __construct(
        private readonly ListViewBuilderFactory $containerBuilderFactory,
    ) {}

    /**
     * @throws FilterException
     */
    public function createFormView(ListViewDto $container): FormView
    {
        return $container->getFormComponent()->createView();
    }

    /**
     * @throws FlareException
     */
    public function getFlare(ListModel|string|int $listModel, array $options = []): ListViewDto
    {
        $cacheKey = $listModel->id . '@' . \md5(\serialize($options));

        if (isset($this->containerCache[$cacheKey])) {
            return $this->containerCache[$cacheKey];
        }

        $listModel = $this->getListModel($listModel);

        $container = $this->containerBuilderFactory
            ->create()
            ->setListModel($listModel)
            ->setFormName($options['form_name'] ?? null)
            ->build();

        $this->containerCache[$cacheKey] = $container;

        return $container;
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