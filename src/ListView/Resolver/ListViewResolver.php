<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\ListView\ListView;
use HeimrichHannot\FlareBundle\Manager\ListViewManager;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class ListViewResolver implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * @throws FlareException
     */
    public function getDetailsPageUrl(ListView $dto, int $id): ?string
    {
        return $this->getListViewManager()->getDetailsPageUrl(
            id: $id,
            listModel: $dto->getListSpecification()->getDataSource(),
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