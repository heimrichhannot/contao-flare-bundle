<?php

namespace HeimrichHannot\FlareBundle\Twig\Runtime;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\ListView\Builder\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;

class FlareRuntime implements RuntimeExtensionInterface
{
    protected array $listViewCache = [];

    public function __construct(
        private readonly ListViewBuilderFactory $listViewBuilderFactory,
    ) {}

    /**
     * @throws FlareException
     */
    public function createFormView(ListViewDto $container): FormView
    {
        return $container->getFormComponent()->createView();
    }

    /**
     * Returns a list view DTO for the given list model.
     *
     * @param array{
     *     form_name?: string,
     *     items_per_page?: int,
     *     sort?: array<string, string>,
     * } $options
     *
     * @throws FlareException
     */
    public function getFlare(ListModel|string|int $listModel, array $options = []): ListViewDto
    {
        $cacheKey = $listModel->id . '@' . \md5(\serialize($options));

        if (isset($this->listViewCache[$cacheKey])) {
            return $this->listViewCache[$cacheKey];
        }

        $listModel = $this->getListModel($listModel);

        $paginatorConfig = new PaginatorConfig(
            itemsPerPage: $options['items_per_page'] ?? null,
        );

        $sortDescriptor = null;
        if (isset($options['sort'])) {
            $sortDescriptor = SortDescriptor::fromMap($options['sort']);
        }

        $listViewDto = $this->listViewBuilderFactory
            ->create()
            ->setListModel($listModel)
            ->setFormName($options['form_name'] ?? null)
            ->setPaginatorConfig($paginatorConfig)
            ->setSortDescriptor($sortDescriptor)
            ->build();

        $this->listViewCache[$cacheKey] = $listViewDto;

        return $listViewDto;
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