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
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Dto\ReaderRequestAttribute;
use HeimrichHannot\FlareBundle\Engine\Context\Factory\ValidationContextFactory;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Event\ReaderRenderEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\ReaderManager;
use HeimrichHannot\FlareBundle\Manager\RequestManager;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\Factory\ListSpecificationFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\RuntimeError;

#[AsContentElement(ReaderController::TYPE, category: 'includes', template: 'content_element/flare_reader')]
final class ReaderController extends AbstractContentElementController
{
    public const TYPE = 'flare_reader';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityCacheTags          $entityCacheTags,
        private readonly KernelInterface          $kernel,
        private readonly ListSpecificationFactory $listSpecificationFactory,
        private readonly LoggerInterface          $logger,
        private readonly ProjectorRegistry        $projectorRegistry,
        private readonly ReaderManager            $readerManager,
        private readonly RequestManager           $requestManager,
        private readonly ResponseContextAccessor  $responseContextAccessor,
        private readonly ScopeMatcher             $scopeMatcher,
        private readonly TranslatorInterface      $translator,
        private readonly TranslationManager       $translationManager,
        private readonly ValidationContextFactory $validationContextFactory,
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

    /**
     * @throws \Exception
     */
    protected function getErrorResponse(?\Exception $e = null): Response
    {
        if (isset($e) && $this->kernel->isDebug()) {
            throw $e;
        }
        /** @noinspection PhpTranslationKeyInspection, PhpTranslationDomainInspection */
        $msg = $this->translator->trans('ERR.flare.listview.malconfigured', [], 'contao_modules');
        return new Response($msg, status: Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @throws \Exception
     */
    protected function getFrontendResponse(Template $template, ContentModel $contentModel, Request $request): Response
    {
        if (!$autoItem = Input::get('auto_item')) {
            throw $this->createNotFoundException('No auto_item supplied.');
        }

        try
        {
            $listModel = $contentModel->getRelated(ContentContainer::FIELD_LIST);

            if (!$listModel instanceof ListModel) {
                throw new FlareException('No list model found.');
            }
        }
        catch (\Exception $e)
        {
            $this->logger->error(\sprintf('%s (tl_content.id=%s)', $e->getMessage(), $contentModel->id),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e]);

            return $this->getErrorResponse($e);
        }

        $errData = [
            "tl_content.id={$contentModel->id}",
            "tl_flare_list.id={$listModel->id}",
        ];

        try
        {
            $listSpec = $this->listSpecificationFactory->create(dataSource: $listModel);

            $validationContext = $this->validationContextFactory->createFromContent(
                contentModel: $contentModel,
                listModel: $listModel
            );

            $validationProjector = $this->projectorRegistry->getProjectorFor($listSpec, $validationContext);
            $validationView = $validationProjector->project($listSpec, $validationContext);
            \assert($validationView instanceof ValidationView, 'Expected ValidationView');

            if (!$autoItemModel = $validationView->getModelByAutoItem($autoItem)) {
                throw $this->createNotFoundException('No model found for the given auto_item.');
            }

            $errData[] = "{$autoItemModel::getTable()}.id={$autoItemModel->id}";

            $this->requestManager->setReader(new ReaderRequestAttribute($autoItemModel, $listSpec));
            $this->entityCacheTags->tagWith($autoItemModel);

            $pageMeta = $this->readerManager->getPageMeta(new ReaderPageMetaConfig(
                contentModel: $contentModel,
                displayModel: $autoItemModel,
                listSpecification: $listSpec,
            ));
        }
        catch (FlareException $e)
        {
            $this->logger->error(\sprintf('%s (%s)', $e->getMessage(), \implode(', ', $errData)),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e]);

            throw new InternalServerErrorHttpException($e->getMessage(), $e);
        }

        $event = $this->eventDispatcher->dispatch(
            new ReaderRenderEvent(
                contentModel: $contentModel,
                context: $validationContext,
                displayModel: $autoItemModel,
                listSpecification: $listSpec,
                pageMeta: $pageMeta,
                template: $template,
            )
        );

        $data = $template->getData();
        $data['flare_reader_spec'] = $listSpec;
        $data['flare_reader_config'] = $validationContext;
        $data['flare_reader'] ??= $validationView;
        /** @deprecated Use 'flare_list' instead. todo(@ericges): Remove in 0.1.0 */
        $data['flare'] = $validationView;
        $data['model'] ??= $event->getDisplayModel();
        $template->setData($data);

        $pageMeta = $event->getPageMeta();
        $this->applyPageMeta($pageMeta);

        try
        {
            return $template->getResponse();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
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
            $listModel = $model->getRelated(ContentContainer::FIELD_LIST);
        } catch (\Exception $e) {
            return new Response($e->getMessage());
        }

        $headline = StringUtil::deserialize($model->headline, true);

        if ($value = $headline['value'] ?? null)
        {
            $unit = $headline['unit'] ?? 'h2';
            $hl = \sprintf('<%s>%s</%s>', $unit, $value, $unit);
        }

        return new Response(\sprintf(
            '%s%s <span class="tl_gray">[%s, %s]</span>',
            $hl ?? '',
            $listModel->title,
            $this->translationManager->listModel($listModel),
            $listModel->dc,
        ));
    }
}