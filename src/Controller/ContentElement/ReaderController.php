<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\InternalServerErrorHttpException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Input;
use Contao\Template;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Contao\Reader\ReaderResolver;
use HeimrichHannot\FlareBundle\Event\ReaderRenderEvent;
use HeimrichHannot\FlareBundle\List\Entry\EntryTaggerInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use HeimrichHannot\FlareBundle\Util\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\RuntimeError;

#[AsContentElement(ReaderController::TYPE, category: 'includes', template: 'content_element/flare_reader')]
final class ReaderController extends AbstractContentElementController
{
    public const TYPE = 'flare_reader';

    public function __construct(
        private readonly EntryTaggerInterface     $entryTagger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface          $logger,
        private readonly ReaderResolver           $readerResolver,
        private readonly ResponseContextAccessor  $responseContextAccessor,
        private readonly ScopeMatcher             $scopeMatcher,
        private readonly TranslatorInterface      $translator,
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
    protected function getFrontendResponse(Template $template, ContentModel $contentModel, Request $request): Response
    {
        // Marks the auto-item as used; the resolver reads it on its own.
        $autoItem = Input::get('auto_item');

        if ($autoItem === null || $autoItem === '') {
            throw $this->createNotFoundException('No auto_item supplied.');
        }

        $resolution = $this->readerResolver->resolve($contentModel);

        if ($e = $resolution->failure)
        {
            // A failed list (a broken filter element or fixed value) arrives here as a FilterException: membership
            // cannot be decided, so the reader answers 5xx, never 404 or the entry.
            $this->logger->error(
                \sprintf(
                    '%s (tl_content.id=%s, %s)',
                    $e->getMessage(),
                    $contentModel->id,
                    $resolution->list->source ?? 'list not built',
                ),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e],
            );

            throw new InternalServerErrorHttpException($e->getMessage(), $e);
        }

        if (!$entry = $resolution->entry) {
            throw $this->createNotFoundException('No entry found for the given auto_item.');
        }

        \assert(
            $resolution->list && $resolution->intent && $resolution->engine && $resolution->view,
            'A resolution with an entry holds everything built on the way.',
        );

        $this->entryTagger->tag($entry);

        $event = $this->eventDispatcher->dispatch(
            new ReaderRenderEvent(
                contentModel: $contentModel,
                intent: $resolution->intent,
                entry: $entry,
                list: $resolution->list,
                template: $template,
            )
        );

        $template = $event->getTemplate();
        $data = $template->getData();
        $data['flare'] = $resolution->engine;
        $data['flare_reader'] = $resolution->view;
        $data['entry'] = $entry;
        $data['content_model'] = $contentModel;
        $data['headline'] = Str::normalizeHeadline($contentModel->headline ?: null);
        $template->setData($data);

        $this->applyPageMeta($resolution->getPageMeta());

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

    public function applyPageMeta(?ReaderPageMeta $pageMeta, ?HtmlHeadBag $htmlHeadBag = null): void
    {
        if (!$pageMeta) {
            return;
        }

        $htmlHeadBag ??= $this->responseContextAccessor->getResponseContext()?->get(HtmlHeadBag::class);

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

        if (!$listModel instanceof ListModel) {
            return new Response(\sprintf(
                '<div><strong class="tl_red">%s</strong></div><div>%s</div>',
                $this->translator->trans('reader.invalid_list', [], 'flare'),
                Str::formatHeadline($model->headline, withTags: true),
            ));
        }

        return new Response(\sprintf(
            '%s%s <span class="tl_gray">[%s, %s]</span>',
            Str::formatHeadline($model->headline, withTags: true),
            $listModel->title,
            $this->translator->trans($listModel->type, [], 'flare_list_driver'),
            $listModel->dc,
        ));
    }
}
