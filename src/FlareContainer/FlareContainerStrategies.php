<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\FlareContainer;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Manager\FilterListManager;
use Symfony\Component\Form\FormInterface;

readonly class FlareContainerStrategies
{
    public function __construct(
        private FilterListManager $filterListManager,
    ) {}

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(FlareContainer $container): array
    {
        return $this->filterListManager->getEntries($container->getListModel(), $container->getFormName());
    }

    /**
     * @throws FilterException
     */
    public function getForm(FlareContainer $container): FormInterface
    {
        return $this->filterListManager->getForm($container->getListModel(), $container->getFormName());
    }

    public function getFormName(FlareContainer $container): string
    {
        return $this->filterListManager->makeFormName($container->getListModel());
    }
}