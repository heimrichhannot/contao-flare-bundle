<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Aura\SqlQuery\QueryFactory;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Builder\FilterFormBuilder;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
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

        $qb = (new QueryBuilder($this->connection))
            ->select('e.*')
            ->from($table, 'e');

        $filterFormTypes = [];
        $filters = [];

        foreach ($filterModels as $filterModel)
        {
            $filterElement = $this->filterElementRegistry->get($filterModel->type);
            if (!$filterElement) {
                continue;
            }

            $filters[] = [$filterModel, $filterElement];

            if ($filterElement->hasFormType()) {
                $filterFormTypes[] = $filterElement->getFormType();
            }
        }

        [$sql, $params] = $this->buildFilteredQuery($filters, $listModel, $table);
        $result = $this->connection->executeQuery($sql, $params);

        $entries = $result->fetchAllAssociative();

        $form = $this->filterFormBuilder->build($filterFormTypes);

        $data = $template->getData();
        $data['flare'] = [];
        $data['flare']['filter_form'] = $form->createView();
        $data['flare']['entries'] = $entries;

        $template->setData($data);

        return new Response($template->parse());
    }

    protected function buildFilteredQuery(array $filters, ListModel $listModel, string $table): array
    {
        $combinedConditions = [];
        $combinedParameters = [];

        $alias = 'main';

        foreach (\array_values($filters) as $i => $filter)
        {
            [$filterModel, $filterElement] = $filter;

            $service = $filterElement->getService();
            $method = $filterElement->getMethod() ?? '__invoke';

            if (!\method_exists($service, $method))
            {
                continue;
            }

            $filterQueryBuilder = new FilterQueryBuilder($this->connection->createExpressionBuilder(), $alias);
            $filterContext = new FilterContext($filterModel, $listModel, $table);

            $service->{$method}($filterQueryBuilder, $filterContext);

            $event = new FilterElementInvokedEvent($filterElement, $filterQueryBuilder, $filterContext, $method);
            $this->eventDispatcher->dispatch($event, "huh.flare.filter_element.{$filterModel->type}.invoked");

            [$sql, $params] = $filterQueryBuilder->buildQuery((string) $i);

            $combinedConditions[] = $sql;
            $combinedParameters = \array_merge($combinedParameters, $params);
        }

        $finalSQL = "SELECT * FROM $table AS $alias";
        if (!empty($combinedConditions))
        {
            $finalSQL .= ' WHERE ' . $this->connection->createExpressionBuilder()->and(...$combinedConditions);
        }

        return [$finalSQL, $combinedParameters];
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return new Response('FLARE Catalog Element');
    }
}