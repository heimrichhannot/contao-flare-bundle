<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Builder\FilterFormBuilder;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(ListViewController::TYPE, category: 'includes', template: 'content_element/flare_listview')]
class ListViewController extends AbstractContentElementController
{
    public const TYPE = 'flare_listview';

    public function __construct(
        private readonly Connection            $connection,
        private readonly FilterFormBuilder     $filterFormBuilder,
        private readonly FilterElementRegistry $filterElementRegistry,
        private readonly ScopeMatcher          $scopeMatcher,
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

        foreach ($filterModels as $filterModel)
        {
            $filterElement = $this->filterElementRegistry->get($filterModel->type);
            if (!$filterElement) {
                continue;
            }

            $service = $filterElement->getService();
            if (\method_exists($service, '__invoke'))
            {
                $innerQB = (new QueryBuilder($this->connection))
                    ->select('1')
                    ->from($table, 'e_inner')
                    ->where('e.id = e_inner.id')
                ;

                $criteria = new Criteria();

                $service->__invoke($innerQB, $filterModel, $listModel, $table);

                $qb->andWhere("EXISTS ({$innerQB->getSQL()})");

                $qb->setParameters(\array_merge($qb->getParameters(), $innerQB->getParameters()));
            }

            if ($filterElement->hasFormType()) {
                $filterFormTypes[] = $filterElement->getFormType();
            }
        }

        $entries = $qb->executeQuery()->fetchAllAssociative();

        $form = $this->filterFormBuilder->build($filterFormTypes);

        $data = $template->getData();
        $data['flare'] = [];
        $data['flare']['filter_form'] = $form->createView();
        $data['flare']['entries'] = $entries;

        $template->setData($data);

        return new Response($template->parse());
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return new Response('FLARE Catalog Element');
    }
}