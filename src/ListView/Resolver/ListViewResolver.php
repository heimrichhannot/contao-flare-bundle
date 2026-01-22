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
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class ListViewResolver implements ListViewResolverInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * @throws FlareException
     */
    public function getEntries(ListView $dto): array
    {
        return $this->getListViewManager()->getEntries(
            listDefinition: $dto->getListDefinition(),
            paginatorConfig: $dto->getPaginatorConfig(),
            sortDescriptor: $dto->getSortDescriptor(),
        );
    }

    /**
     * @throws FlareException
     */
    public function getModel(ListView $dto, int $id): Model
    {
        return $this->getListViewManager()->getModel(
            id: $id,
            listDefinition: $dto->getListDefinition(),
        );
    }

    /**
     * @throws FilterException
     */
    public function getForm(ListView $dto): FormInterface
    {
        return $this->getListViewManager()->getForm(
            listDefinition: $dto->getListDefinition(),
        );
    }

    /**
     * @throws FlareException
     */
    public function getPaginator(ListView $dto): Paginator
    {
        return $this->getListViewManager()->getPaginator(
            listDefinition: $dto->getListDefinition(),
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
        return $this->getListViewManager()->getSortDescriptor(
            listDefinition: $dto->getListDefinition(),
        );
    }

    /**
     * @throws FlareException
     */
    public function getDetailsPageUrl(ListView $dto, int $id): ?string
    {
        return $this->getListViewManager()->getDetailsPageUrl(
            id: $id,
            listModel: $dto->getListModel(),
            contentContext: $dto->getContentContext(),
        );
    }

    protected function getListViewManager(): ListViewManager
    {
        return $this->container->get(ListViewManager::class)
            ?? throw new \RuntimeException('ListViewManager not found');
    }

    public static function getSubscribedServices(): array
    {
        return [
            ListViewManager::class,
        ];
    }
}