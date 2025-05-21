<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;

interface ListViewResolverInterface
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(ListViewDto $dto): array;

    public function getModel(ListViewDto $dto, int $id): Model;

    /**
     * @throws FlareException
     */
    public function getForm(ListViewDto $dto): FormInterface;

    public function getPaginator(ListViewDto $dto): Paginator;

    public function getPaginatorConfig(ListViewDto $dto): PaginatorConfig;

    public function getSortDescriptor(ListViewDto $dto): ?SortDescriptor;

    public function getDetailsPageUrl(ListViewDto $dto, int $id): ?string;
}