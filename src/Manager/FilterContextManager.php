<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Controller;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\HydrateFormContract;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

readonly class FilterContextManager
{
    public function __construct(
        private ChoicesBuilderFactory    $choicesBuilderFactory,
        private Connection               $connection,
        private EventDispatcherInterface $eventDispatcher,
        private FilterElementRegistry    $filterElementRegistry,
        private FormFactoryInterface     $formFactory,
    ) {}

    public function collect(ListModel $listModel): ?FilterContextCollection
    {
        if (!$listModel->id || !$table = $listModel->dc) {
            return null;
        }

        $filterModels = FilterModel::findByPid($listModel->id, published: true);

        if (!$filterModels->count()) {
            return null;
        }

        Controller::loadDataContainer($table);

        $filters = FilterContextCollection::create($listModel);

        foreach ($filterModels as $filterModel)
        {
            if (!$filterModel->published) {
                continue;
            }

            $filterElementAlias = $filterModel->type;

            if (!$config = $this->filterElementRegistry->get($filterElementAlias)) {
                continue;
            }

            $filters->add(new FilterContext($listModel, $filterModel, $config, $filterElementAlias, $table));
        }

        return $filters;
    }

    /**
     * @throws FilterException If the form could not be built
     */
    public function buildForm(FilterContextCollection $filters, string $name): FormInterface
    {
        $builder = $this->formFactory->createNamedBuilder($name, FormType::class, null, [
            'method'             => 'GET',
            'csrf_protection'    => false,
            'translation_domain' => 'flare_form',
            'attr' => [
                'data-flare-keep-query' => 'true',
            ],
        ]);

        $defaultOptions = [
            'inherit_data'                    => false,
            'label'                           => false,
        ];

        foreach ($filters->getIterator() as $filter)
        {
            $formType = $filter->getConfig()->getFormType();
            $filterElement = $filter->getConfig()->getService();
            $filterModel = $filter->getFilterModel();

            if (!$formType || !$filterElement || !$filterModel)
            {
                continue;
            }

            if (!$filterModel->published || !$filterModel->type || $filterModel->intrinsic)
            {
                continue;
            }

            if ($filterElement instanceof FormTypeOptionsContract)
            {
                try
                {
                    $choicesBuilder = $this->choicesBuilderFactory->createChoicesBuilder();
                    $generatedOptions = $filterElement->getFormTypeOptions($filter, $choicesBuilder);

                    $choicesOptions = $choicesBuilder->isEnabled() ? [
                        'choices' => $choicesBuilder->buildChoices(),
                        'choice_label' => $choicesBuilder->buildChoiceLabelCallback(),
                    ] : [];

                    $options = \array_merge($defaultOptions, $choicesOptions, $generatedOptions);
                }
                catch (FilterException $e)
                {
                    $method = $e->getMethod() ?? ($filterElement::class . '::getFormTypeOptions');

                    throw new FilterException(
                        \sprintf('[FLARE] Form denied: %s', $e->getMessage()),
                        code: $e->getCode(), previous: $e, method: $method,
                        source: \sprintf('tl_flare_filter.id=%s', $filterModel->id)
                    );
                }
            }
            else
            {
                $options = $defaultOptions;
            }

            $key = $filter->getFilterModel()->id;

            $builder->add($key, $formType, $options);
        }

        /*if ($builder->count())
        {
            $builder->add('submit', SubmitType::class, [
                'label' => 'submit',
            ]);
        }*/

        return $builder->getForm();
    }

    public function hydrateForm(FilterContextCollection $filters, FormInterface $form): void
    {
        if ($form->isSubmitted()) {
            return;
        }

        foreach ($filters->getIterator() as $filter)
        {
            $filterElement = $filter->getConfig()->getService();
            $filterModel = $filter->getFilterModel();

            if (!$filterModel || !$filterElement instanceof HydrateFormContract)
            {
                continue;
            }

            if (!$filterModel->published || !$filterModel->type || $filterModel->intrinsic)
            {
                continue;
            }

            if (!$form->has((string) $filterModel->id))
            {
                continue;
            }

            $field = $form->get((string) $filterModel->id);

            $filterElement->hydrateForm($filter, $field);
        }
    }

    public function hydrateFilterElements(FilterContextCollection $filters, FormInterface $form): void
    {
        if ($form->isSubmitted() && !$form->isValid()) {
            return;
        }

        foreach ($filters->getIterator() as $filter)
        {
            $filterElement = $filter->getConfig()->getService();
            $filterModel = $filter->getFilterModel();

            if (!$filterElement || !$filterModel)
            {
                continue;
            }

            if (!$filterModel->published || !$filterModel->type || $filterModel->intrinsic)
            {
                continue;
            }

            if (!$form->has((string) $filterModel->id))
            {
                continue;
            }

            $field = $form->get((string) $filterModel->id);

            $data = $field->getData();

            if (isset($data))
            {
                $filter->setSubmittedData($data);
            }
        }
    }

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     *
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchEntries(FilterContextCollection $filters, ?Paginator $paginator = null): array
    {
        [$sql, $params, $types] = $this->buildFilteredQuery(
            filters: $filters,
            limit: $paginator?->getItemsPerPage() ?: null,
            offset: $paginator?->getOffset() ?: null,
        );

        $result = $this->connection->executeQuery($sql, $params, $types);

        $entries = $result->fetchAllAssociative();

        $result->free();

        return $entries;
    }

    public function fetchCount(FilterContextCollection $filters): int
    {
        [$sql, $params, $types] = $this->buildFilteredQuery($filters, isCounting: true);

        $result = $this->connection->executeQuery($sql, $params, $types);

        $count = $result->fetchOne() ?: 0;

        $result->free();

        return $count;
    }

    /**
     * @throws FilterException
     */
    public function buildFilteredQuery(
        FilterContextCollection $filters,
        ?int                    $limit = null,
        ?int                    $offset = null,
        ?string                 $order = null,
        bool                    $isCounting = false,
    ): array {
        $combinedConditions = [];
        $combinedParameters = [];
        $combinedTypes = [];

        $table = $filters->getTable();
        $as = 'main';

        if (!Str::isValidSqlName($table)) {
            throw new FilterException(\sprintf('[FLARE] Invalid table name: %s', $table), method: __METHOD__);
        }

        $blockResult = ["SELECT 1 FROM `$table` as $as LIMIT 0", [], []];
        if ($isCounting) {
            $blockResult[0] = "SELECT COUNT(*) FROM `$table` AS $as";
        }

        foreach ($filters as $i => $filter)
        {
            $config = $filter->getConfig();

            $service = $config->getService();
            $method = $config->getMethod() ?? '__invoke';

            if (!\method_exists($service, $method))
            {
                continue;
            }

            $filterQueryBuilder = new FilterQueryBuilder($this->connection->createExpressionBuilder(), $as);

            try
            {
                $service->{$method}($filter, $filterQueryBuilder);
            }
            catch (FilterException $e)
            {
                $method = $e->getMethod() ?? ($service::class . '::' . $method);

                throw new FilterException(
                    \sprintf('[FLARE] Query denied: %s', $e->getMessage()),
                    code: $e->getCode(), previous: $e, method: $method,
                    source: \sprintf('tl_flare_filter.id=%s', $filter->getFilterModel()?->id)
                );
            }

            $event = new FilterElementInvokedEvent($filter, $filterQueryBuilder, $method);
            $this->eventDispatcher->dispatch($event, "huh.flare.filter_element.{$filter->getFilterAlias()}.invoked");

            if ($filterQueryBuilder->isBlocking())
            {
                return $blockResult;
            }

            [$sql, $params, $types] = $filterQueryBuilder->buildQuery((string) $i);

            if (empty($sql))
            {
                continue;
            }

            $combinedConditions[] = $sql;
            $combinedParameters = \array_merge($combinedParameters, $params);
            $combinedTypes = \array_merge($combinedTypes, $types);
        }

        $finalSQL = $isCounting ? "SELECT COUNT(*)" : "SELECT *";
        $finalSQL .= " FROM `$table` AS $as WHERE ";
        $finalSQL .= empty($combinedConditions) ? '1' : $this->connection->createExpressionBuilder()->and(...$combinedConditions);

        if (!$isCounting)
        {
            if (isset($limit)) $finalSQL .= " LIMIT $limit";
            if (isset($offset)) $finalSQL .= " OFFSET $offset";
            if (isset($order)) $finalSQL .= " ORDER BY $order";
        }

        return [$finalSQL, $combinedParameters, $combinedTypes];
    }
}