<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use Symfony\Component\Form\FormInterface;

interface ListViewResolverInterface
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(ListViewDto $dto): array;

    /**
     * @throws FlareException
     */
    public function getForm(ListViewDto $dto): FormInterface;

    public function getFormName(ListViewDto $dto): string;

    public function getPaginator(ListViewDto $dto): Paginator;

    public function getPaginatorConfig(ListViewDto $dto): PaginatorConfig;
}