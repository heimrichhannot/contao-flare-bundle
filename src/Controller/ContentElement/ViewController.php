<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use HeimrichHannot\FlareBundle\Builder\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Manager\FilterElementManager;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(ViewController::TYPE, category: 'includes', template: 'content_element/flare_listview')]
class ViewController extends AbstractContentElementController
{
    public const TYPE = 'flare_listview';

    public function __construct(
        private readonly ScopeMatcher         $scopeMatcher,
        private readonly FilterFormBuilder    $filterFormBuilder,
        private readonly FilterElementManager $filterElementManager,
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

        $catalog = $model->getRelated('flare_catalog') ?? null;
        if (!$catalog instanceof ListModel) {
            return new Response();
        }

        $filters = FilterModel::findByPid($catalog->id, published: true);

        $filterElements = [];
        $filterFormTypes = [];

        foreach ($filters as $filter)
        {
            $filterElementDTO = $this->filterElementManager->getDTO($filter->type);
            if (!$filterElementDTO) {
                continue;
            }

            $attribute = $filterElementDTO->getAttribute();
            $filterElements[] = $filterElementDTO;

            if ($attribute->hasFormType()) {
                $filterFormTypes[] = $attribute->getFormType();
            }
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