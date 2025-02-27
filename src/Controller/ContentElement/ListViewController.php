<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use HeimrichHannot\FlareBundle\Builder\FilterFormBuilder;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(ListViewController::TYPE, category: 'includes', template: 'content_element/flare_listview')]
class ListViewController extends AbstractContentElementController
{
    public const TYPE = 'flare_listview';

    public function __construct(
        private readonly ScopeMatcher          $scopeMatcher,
        private readonly FilterFormBuilder     $filterFormBuilder,
        private readonly FilterElementRegistry $filterElementRegistry,
    ) {}

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return $this->scopeMatcher->isFrontendRequest($request)
            ? $this->getFrontendResponse($template, $model, $request)
            : $this->getBackendResponse($template, $model, $request);
    }

    protected function getFrontendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        \dump($model);

        $catalog = $model->getRelated(ContentContainer::FIELD_LIST) ?? null;
        if (!$catalog instanceof ListModel) {
            return new Response();
        }

        $filters = FilterModel::findByPid($catalog->id, published: true);

        $filterElements = [];
        $filterFormTypes = [];

        foreach ($filters as $filter)
        {
            $filterElement = $this->filterElementRegistry->get($filter->type);
            if (!$filterElement) {
                continue;
            }

            $filterElements[] = $filterElement;
            if ($filterElement->hasFormType()) {
                $filterFormTypes[] = $filterElement->getFormType();
            }

            \dump($filterElement);
        }

        $form = $this->filterFormBuilder->build($filterFormTypes);

        \dump($filters, $filterElements, $filterFormTypes);

        $data = $template->getData();
        $data['flare'] = [];
        $data['flare']['filter_form'] = $form->createView();
        $template->setData($data);
        return new Response($template->parse());
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return new Response('FLARE Catalog Element');
    }
}