<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Contract\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

readonly class FilterContextManager
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private FormFactoryInterface $formFactory
    ) {}

    public function collect(ListModel $listModel): ?FilterContextCollection
    {
        if (!$listModel->id || !$listModel->published || !$table = $listModel->dc)
        {
            return null;
        }

        $filterModels = FilterModel::findByPid($listModel->id, published: true);

        if (!$listModel->dc || !$filterModels->count()) {
            return null;
        }

        Controller::loadDataContainer($table);

        $filters = new FilterContextCollection();
        $filters->setTable($table);

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
            'csrf_protection' => false,
            'method'          => 'GET',
        ]);

        $defaultOptions = [
            'inherit_data' => false,
            'label'        => false,
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
                    $options = \array_merge(
                        $defaultOptions,
                        $filterElement->getFormTypeOptions($filter)
                    );
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

        if ($builder->count())
        {
            $builder->add('submit', SubmitType::class, [
                'label' => 'Filter',
            ]);
        }

        return $builder->getForm();
    }

    public function hydrate(FilterContextCollection $filters, FormInterface $form): void
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
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
}