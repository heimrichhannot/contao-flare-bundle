<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Builder\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(ListViewController::TYPE, category: 'includes', template: 'content_element/flare_listview')]
class ListViewController extends AbstractContentElementController
{
    public const TYPE = 'flare_listview';

    public function __construct(
        private readonly Connection               $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterFormBuilder        $filterFormBuilder,
        private readonly FilterElementRegistry    $filterElementRegistry,
        private readonly ScopeMatcher             $scopeMatcher,
    ) {}

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return $this->scopeMatcher->isFrontendRequest($request)
            ? $this->getFrontendResponse($template, $model, $request)
            : $this->getBackendResponse($template, $model, $request);
    }

    protected function getFrontendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        try {
            /** @var ?ListModel $listModel */
            $listModel = $model->getRelated(ContentContainer::FIELD_LIST) ?? null;
        } catch (\Exception $e) {
            return new Response();
        }

        if (!$listModel instanceof ListModel
            || !$listModel->id
            || !$listModel->published
            || !$table = $listModel->dc)
        {
            return new Response();
        }

        $filterModels = FilterModel::findByPid($listModel->id, published: true);

        if (!$listModel->dc || !$filterModels->count()) {
            return new Response();
        }

        Controller::loadDataContainer($table);

        $filters = new FilterContextCollection();

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

        [$sql, $params, $types] = $this->buildFilteredQuery($filters, $table);
        $result = $this->connection->executeQuery($sql, $params, $types);

        $entries = $result->fetchAllAssociative();

        $result->free();

        $form = $this->filterFormBuilder->build($filters->collectFormTypes());

        $data = $template->getData();
        $data['flare'] = [];
        $data['flare']['filter_form'] = $form->createView();
        $data['flare']['entries'] = $entries;

        $template->setData($data);

        return new Response($template->parse());
    }

    protected function buildFilteredQuery(FilterContextCollection $filters, string $table): array
    {
        $combinedConditions = [];
        $combinedParameters = [];
        $combinedTypes = [];

        $as = 'main';

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

            $service->{$method}($filterQueryBuilder, $filter);

            $event = new FilterElementInvokedEvent($filter, $filterQueryBuilder, $method);
            $this->eventDispatcher->dispatch($event, "huh.flare.filter_element.{$filter->getFilterAlias()}.invoked");

            [$sql, $params, $types] = $filterQueryBuilder->buildQuery((string) $i);

            if (empty($sql))
            {
                continue;
            }

            $combinedConditions[] = $sql;
            $combinedParameters = \array_merge($combinedParameters, $params);
            $combinedTypes = \array_merge($combinedTypes, $types);
        }

        $finalSQL = "SELECT * FROM $table AS $as";
        if (!empty($combinedConditions))
        {
            $finalSQL .= ' WHERE ' . $this->connection->createExpressionBuilder()->and(...$combinedConditions);
        }

        return [$finalSQL, $combinedParameters, $combinedTypes];
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return new Response('FLARE ListView Element');
    }
}