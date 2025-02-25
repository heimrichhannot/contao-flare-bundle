<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\LicenseElement;
use HeimrichHannot\FlareBundle\FilterForm\FilterFormBuilder;
use HeimrichHannot\FlareBundle\FormType\LicenseFilterType;
use HeimrichHannot\FlareBundle\FormType\PublishedFilterType;
use HeimrichHannot\FlareBundle\Manager\FilterElementManager;
use HeimrichHannot\FlareBundle\Model\CatalogFilterModel;
use HeimrichHannot\FlareBundle\Model\CatalogModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(CatalogElementController::TYPE, category: 'includes', template: 'content_element/flare_catalog')]
class CatalogElementController extends AbstractContentElementController
{
    public const TYPE = 'flare_catalog';

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
        if (!$catalog instanceof CatalogModel) {
            return new Response();
        }

        $filters = CatalogFilterModel::findByPid($catalog->id, published: true);

        $filterElements = [];
        $filterFormTypes = [];

        foreach ($filters as $filter)
        {
            $filterElementDTO = $this->filterElementManager->getFilterElement($filter->type);
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