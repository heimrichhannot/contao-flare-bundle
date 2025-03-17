<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Manager\FilterListManager;
use Symfony\Component\Form\FormInterface;

readonly class ListViewResolver implements ListViewResolverInterface
{
    public function __construct(
        private FilterListManager $filterListManager,
    ) {}

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(ListViewDto $dto): array
    {
        return $this->filterListManager->getEntries($dto->getListModel(), $dto->getFormName(), $dto->getPaginatorConfig());
    }

    /**
     * @throws FilterException
     */
    public function getForm(ListViewDto $dto): FormInterface
    {
        return $this->filterListManager->getForm($dto->getListModel(), $dto->getFormName());
    }

    public function getFormName(ListViewDto $dto): string
    {
        return $this->filterListManager->makeFormName($dto->getListModel());
    }

    public function getPaginator(ListViewDto $dto): Paginator
    {
        return $this->filterListManager->getPaginator($dto->getListModel(), $dto->getFormName(), $dto->getPaginatorConfig());
    }

    public function getPaginatorConfig(ListViewDto $dto): PaginatorConfig
    {
        return new PaginatorConfig(itemsPerPage: 0);
    }
}