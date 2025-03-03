<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractFilterElement implements TranslatorInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {}

    abstract public function __invoke(
        QueryBuilder $queryBuilder,
        FilterModel  $filterModel,
        ListModel    $listModel,
        string       $table
    ): void;

    public function formTypeOptions(): array
    {
        return [];
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans('filter_element.' . $id, $parameters, $domain ?? 'flare', $locale);
    }
}