<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Manager\FilterContextManager;
use HeimrichHannot\FlareBundle\Manager\FilterQueryManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(ListViewController::TYPE, category: 'includes', template: 'content_element/flare_listview')]
class ListViewController extends AbstractContentElementController
{
    public const TYPE = 'flare_listview';

    public function __construct(
        private readonly FilterContextManager     $contextManager,
        private readonly FilterQueryManager       $queryManager,
        private readonly LoggerInterface          $logger,
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

        if (!$listModel instanceof ListModel) {
            return new Response();
        }

        if (!$filters = $this->contextManager->collect($listModel)) {
            return new Response();
        }

        try
        {
            $formName = $model->flare_formName ?: ('fl' . $listModel->id);
            $form = $this->contextManager->buildForm($filters, $formName);

            $form->handleRequest($request);

            $this->contextManager->hydrate($filters, $form);

            $entries = $this->queryManager->fetch($filters);
        }
        catch (FilterException $e)
        {
            $this->logger->error(
                \sprintf(
                    '%s (tl_content.id=%s, tl_flare_list.id=%s)',
                    $e->getMessage(), $model->id, $listModel->id
                ),
                ['contao' => new ContaoContext($e->getMethod() ?? __METHOD__, ContaoContext::ERROR, source: $e->getSource()),
                 'exception' => $e]
            );

            return new Response('Error. This filtered list is malconfigured. See log for details.');
        }

        $data = $template->getData();
        $data['flare'] = [];
        $data['flare']['filter_form'] = $form->createView();
        $data['flare']['entries'] = $entries;

        $template->setData($data);

        return new Response($template->parse());
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        try {
            /** @var ?ListModel $listModel */
            $listModel = $model->getRelated(ContentContainer::FIELD_LIST) ?? null;
        } catch (\Exception $e) {
            return new Response();
        }

        return new Response(\sprintf('%s [%s]', $listModel->title, $listModel->type));
    }
}