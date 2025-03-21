<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Manager\ListViewManager;
use Symfony\Component\Form\FormInterface;

readonly class ListViewResolver implements ListViewResolverInterface
{
    public function __construct(
        private ListViewManager $manager,
    ) {}

    /**
     * @throws FlareException
     */
    public function getEntries(ListViewDto $dto): array
    {
        return $this->manager->getEntries($dto->getListModel(), $dto->getFormName(), $dto->getPaginatorConfig());
    }

    /**
     * @throws FlareException
     */
    public function getModel(ListViewDto $dto, int $id): Model
    {
        return $this->manager->getModel($dto->getListModel(), $dto->getFormName(), $id);
    }

    /**
     * @throws FilterException
     */
    public function getForm(ListViewDto $dto): FormInterface
    {
        return $this->manager->getForm($dto->getListModel(), $dto->getFormName());
    }

    public function getFormName(ListViewDto $dto): string
    {
        return $this->manager->makeFormName($dto->getListModel());
    }

    /**
     * @throws FlareException
     */
    public function getPaginator(ListViewDto $dto): Paginator
    {
        return $this->manager->getPaginator($dto->getListModel(), $dto->getFormName(), $dto->getPaginatorConfig());
    }

    public function getPaginatorConfig(ListViewDto $dto): PaginatorConfig
    {
        return new PaginatorConfig(itemsPerPage: 0);
    }

    /**
     * @throws FlareException
     */
    public function getDetailsPageUrl(ListViewDto $dto, int $id): ?string
    {
        return $this->manager->getDetailsPageUrl($dto->getListModel(), $dto->getFormName(), $id);
    }
}