<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\ListView;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;

interface ListViewResolverInterface
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(ListView $dto): array;

    public function getModel(ListView $dto, int $id): Model;

    /**
     * @throws FlareException
     */
    public function getForm(ListView $dto): FormInterface;

    public function getPaginator(ListView $dto): Paginator;

    public function getPaginatorConfig(ListView $dto): PaginatorConfig;

    public function getSortDescriptor(ListView $dto): ?SortDescriptor;

    public function getDetailsPageUrl(ListView $dto, int $id): ?string;
}