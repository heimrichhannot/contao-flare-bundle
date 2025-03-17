<?php

namespace HeimrichHannot\FlareBundle\ListView\Resolver;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use Symfony\Component\Form\FormInterface;

interface ListViewResolverInterface
{
    public function getEntries(ListViewDto $dto): array;

    /**
     * @throws FlareException
     */
    public function getForm(ListViewDto $dto): FormInterface;

    public function getFormName(ListViewDto $dto): string;
}