<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(CatalogElementController::TYPE, category: 'includes', template: 'content_element/flare_catalog')]
class CatalogElementController extends AbstractContentElementController
{
    public const TYPE = 'flare_catalog';

    public function __construct(
        private readonly ScopeMatcher $scopeMatcher
    ) {}

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return $this->scopeMatcher->isFrontendRequest($request)
            ? $this->getFrontendResponse($template, $model, $request)
            : $this->getBackendResponse($template, $model, $request);
    }

    protected function getFrontendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return new Response($template->parse());
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return new Response('FLARE Catalog Element');
    }
}