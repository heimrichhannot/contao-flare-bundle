<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Cache\EntityCacheTags;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\InternalServerErrorHttpException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Input;
use Contao\StringUtil;
use Contao\Template;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Event\ReaderBuiltEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\ReaderManager;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\RuntimeError;

#[AsContentElement(ReaderController::TYPE, category: 'includes', template: 'content_element/flare_reader')]
class ReaderController extends AbstractContentElementController
{
    public const TYPE = 'flare_reader';

    public function __construct(
        private readonly EntityCacheTags          $entityCacheTags,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface          $logger,
        private readonly ReaderManager            $readerManager,
        private readonly ResponseContextAccessor  $responseContextAccessor,
        private readonly ScopeMatcher             $scopeMatcher,
        private readonly TranslationManager       $translator,
    ) {}

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        return $this->scopeMatcher->isFrontendRequest($request)
            ? $this->getFrontendResponse($template, $model, $request)
            : $this->getBackendResponse($template, $model, $request);
    }

    protected function getFrontendResponse(Template $template, ContentModel $contentModel, Request $request): Response
    {
        if (!$autoItem = Input::get('auto_item')) {
            throw $this->createNotFoundException('No auto_item supplied.');
        }

        $errData = ['tl_content.id' => $contentModel->id];

        try
        {
            /** @var ?ListModel $listModel */
            $listModel = $contentModel->getRelated(ContentContainer::FIELD_LIST);

            if (!$listModel instanceof ListModel) {
                throw new FilterException('No list model found.');
            }

            $errData['tl_flare_list.id'] = $listModel->id;

            $contentContext = new ContentContext(
                context: ContentContext::CONTEXT_READER,
                contentModel: $contentModel,
            );

            $model = $this->readerManager->getModelByAutoItem(
                autoItem: $autoItem,
                listModel: $listModel,
                contentContext: $contentContext,
            );

            if (!isset($model)) {
                throw $this->createNotFoundException('No model found.');
            }

            $errData[$model->getTable() . '.id'] = $model->id;

            $this->entityCacheTags->tagWith($model);

            $pageMeta = $this->readerManager->getPageMeta(
                listModel: $listModel,
                model: $model,
                contentContext: $contentContext,
                contentModel: $contentModel,
            );
        }
        catch (FlareException $e)
        {
            $this->logger->error(\sprintf('%s (%s)', $e->getMessage(), \implode(', ', $errData)),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e]);

            throw new InternalServerErrorHttpException($e->getMessage(), $e);
        }

        $data = ['model' => $model];

        $event = new ReaderBuiltEvent(
            contentContext: $contentContext,
            contentModel: $contentModel,
            displayModel: $model,
            listModel: $listModel,
            pageMeta: $pageMeta,
            template: $template,
            data: $data,
        );

        $this->eventDispatcher->dispatch($event, 'flare.reader.built');

        $template->setData($event->getData() + $template->getData());

        $pageMeta = $event->getPageMeta();
        $this->applyPageMeta($pageMeta);

        try
        {
            return $template->getResponse();
        }
        catch (RuntimeError $e)
        {
            $previous = $e;
            while ($previous = $previous->getPrevious()) {
                if ($previous instanceof ResponseException) {
                    throw $previous;
                }
            }

            throw $e;
        }
    }

    public function applyPageMeta(ReaderPageMetaDto $pageMeta, ?HtmlHeadBag $htmlHeadBag = null): void
    {
        $htmlHeadBag ??= $this->responseContextAccessor?->getResponseContext()?->get(HtmlHeadBag::class);

        if (!$htmlHeadBag) {
            return;
        }

        $htmlHeadBag->setTitle($pageMeta->getTitle());

        if ($description = $pageMeta->getDescription()) {
            $htmlHeadBag->setMetaDescription($description);
        }

        if ($canonical = $pageMeta->getCanonical()) {
            $htmlHeadBag->setCanonicalUri($canonical);
        }

        if ($robots = $pageMeta->getRobots()) {
            $htmlHeadBag->setMetaRobots($robots);
        }
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): Response
    {
        try {
            /** @var ?ListModel $listModel */
            $listModel = $model->getRelated(ContentContainer::FIELD_LIST) ?? null;
        } catch (\Exception $e) {
            return new Response($e->getMessage());
        }

        if (($headline = StringUtil::deserialize($model->headline, true)) && !empty($headline['value'])) {
            $unit = !empty($headline['unit']) ? $headline['unit'] : 'h2';
            $hl = \sprintf('<%s>%s</%s>', $unit, $headline['value'], $unit);
        }

        return new Response(
            ($hl ?? '') . \sprintf(
                '%s <span class="tl_gray">[%s, %s]</span>',
                $listModel->title,
                $this->translator->listModel($listModel),
                $listModel->dc,
            ),
        );
    }
}