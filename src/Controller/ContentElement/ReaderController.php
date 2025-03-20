<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\InternalServerErrorHttpException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Input;
use Contao\Template;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\ListView\Builder\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Manager\ReaderManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(ReaderController::TYPE, category: 'includes', template: 'content_element/flare_reader')]
class ReaderController extends AbstractContentElementController
{
    public const TYPE = 'flare_reader';

    public function __construct(
        private readonly ListViewBuilderFactory $listViewBuilderFactory,
        private readonly LoggerInterface        $logger,
        private readonly ReaderManager          $readerManager,
        private readonly ScopeMatcher           $scopeMatcher,
    ) {}

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return $this->scopeMatcher->isFrontendRequest($request)
            ? $this->getFrontendResponse($template, $model, $request)
            : $this->getBackendResponse($template, $model, $request);
    }

    protected function getFrontendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        if (!$autoItem = Input::get('auto_item')) {
            throw $this->createNotFoundException('No auto_item supplied.');
        }

        try
        {
            /** @var ?ListModel $listModel */
            $listModel = $model->getRelated(ContentContainer::FIELD_LIST) ?? null;

            if (!$listModel instanceof ListModel) {
                throw new FilterException('No list model found.');
            }
        }
        catch (\Exception $e)
        {
            $this->logger->error(\sprintf('%s (tl_content.id=%s)', $e->getMessage(), $model->id),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e]);

            throw new InternalServerErrorHttpException($e->getMessage(), $e);
        }

        try
        {
            $model = $this->readerManager->getModel($listModel, $autoItem);
        }
        catch (FlareException $e)
        {
            $this->logger->error(\sprintf('%s (tl_content.id=%s, tl_flare_list.id=%s)', $e->getMessage(), $model->id, $listModel->id),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e]);

            throw new InternalServerErrorHttpException($e->getMessage(), $e);
        }

        if (!isset($model)) {
            throw $this->createNotFoundException('No model found.');
        }

        $data = ['model' => $model];

        $template->setData($data + $template->getData());

        return $template->getResponse();
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        return new Response('Backend');
    }
}