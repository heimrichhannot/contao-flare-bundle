<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
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

    public function getModel(ListViewDto $dto, int $id): Model
    {
        return $this->filterListManager->getModel($dto->getListModel(), $dto->getFormName(), $id);
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

    public function getDetailsPageUrl(ListViewDto $dto, int $id): ?string
    {
        return $this->filterListManager->getDetailsPageUrl($dto->getListModel(), $dto->getFormName(), $id);
    }
}