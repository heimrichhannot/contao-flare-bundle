<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\ListView;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Manager\ListViewManager;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;

readonly class ListViewResolver implements ListViewResolverInterface
{
    public function __construct(
        private ListViewManager $manager,
    ) {}

    /**
     * @throws FlareException
     */
    public function getEntries(ListView $dto): array
    {
        return $this->manager->getEntries(
            listModel: $dto->getListModel(),
            contentContext: $dto->getContentContext(),
            paginatorConfig: $dto->getPaginatorConfig(),
            sortDescriptor: $dto->getSortDescriptor(),
        );
    }

    /**
     * @throws FlareException
     */
    public function getModel(ListView $dto, int $id): Model
    {
        return $this->manager->getModel(
            id: $id,
            listModel: $dto->getListModel(),
            contentContext: $dto->getContentContext(),
        );
    }

    /**
     * @throws FilterException
     */
    public function getForm(ListView $dto): FormInterface
    {
        return $this->manager->getForm(
            listModel: $dto->getListModel(),
            contentContext: $dto->getContentContext(),
        );
    }

    /**
     * @throws FlareException
     */
    public function getPaginator(ListView $dto): Paginator
    {
        return $this->manager->getPaginator(
            listModel: $dto->getListModel(),
            contentContext: $dto->getContentContext(),
            paginatorConfig: $dto->getPaginatorConfig(),
        );
    }

    public function getPaginatorConfig(ListView $dto): PaginatorConfig
    {
        return new PaginatorConfig(itemsPerPage: 0);
    }

    /**
     * @throws FlareException
     */
    public function getSortDescriptor(ListView $dto): ?SortDescriptor
    {
        return $this->manager->getSortDescriptor(
            listModel: $dto->getListModel(),
            contentContext: $dto->getContentContext(),
        );
    }

    /**
     * @throws FlareException
     */
    public function getDetailsPageUrl(ListView $dto, int $id): ?string
    {
        return $this->manager->getDetailsPageUrl(
            id: $id,
            listModel: $dto->getListModel(),
            contentContext: $dto->getContentContext(),
        );
    }
}