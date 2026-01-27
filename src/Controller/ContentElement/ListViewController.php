<?php /** @noinspection RedundantSuppression */

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\StringUtil;
use Contao\Template;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Event\ListViewRenderEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Factory\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListContextBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListDefinitionBuilderFactory;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Projector\Projectors;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsContentElement(ListViewController::TYPE, category: 'includes', template: 'content_element/flare_listview')]
final class ListViewController extends AbstractContentElementController
{
    public const TYPE = 'flare_listview';

    public function __construct(
        private readonly EventDispatcherInterface     $eventDispatcher,
        private readonly KernelInterface              $kernel,
        private readonly ListContextBuilderFactory    $listContextBuilderFactory,
        private readonly ListDefinitionBuilderFactory $listDefinitionBuilderFactory,
        private readonly ListViewBuilderFactory       $listViewBuilderFactory,
        private readonly LoggerInterface              $logger,
        private readonly Projectors                   $projectors,
        private readonly ScopeMatcher                 $scopeMatcher,
        private readonly SymfonyResponseTagger        $responseTagger,
        private readonly TranslationManager           $translationManager,
        private readonly TranslatorInterface          $translator,
        private readonly PaginatorBuilderFactory      $paginatorBuilder,
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
        return new Response($msg);
    }

    /**
     * @throws \Exception
     */
    protected function getFrontendResponse(Template $template, ContentModel $contentModel, Request $request): Response
    {
        try
        {
            /** @var ?ListModel $listModel */
            $listModel = $contentModel->getRelated(ContentContainer::FIELD_LIST);

            if (!$listModel instanceof ListModel) {
                throw new FilterException('No list model found.');
            }
        }
        catch (\Exception $e)
        {
            $this->logger->error(\sprintf('%s (tl_content.id=%s)', $e->getMessage(), $contentModel->id),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e]);

            return $this->getErrorResponse($e);
        }

        try
        {
            $filterFormName = $contentModel->flare_formName ?: 'fl' . $listModel->id;

            $paginatorConfig = $this->paginatorBuilder->create()
                ->fromConfig(new PaginatorConfig(
                    itemsPerPage: (int) ($contentModel->flare_itemsPerPage ?: 0),
                ))
                ->queryPrefix($filterFormName)
                ->handleRequest($request)
                ->buildConfig();

            $sortDescriptor = $this->getSortDescriptor($listModel);

            $listContext = $this->listContextBuilderFactory->create()
                ->setPaginatorConfig($paginatorConfig)
                ->setSortDescriptor($sortDescriptor)
                ->setContentModel($contentModel)
                ->set('form.action_page', (int) $contentModel->flare_jumpTo)
                ->set('form.name', $filterFormName)
                ->build();

            $listDefinition = $this->listDefinitionBuilderFactory->create()
                ->setDataSource($listModel)
                ->build();

            if (!$projector = $this->projectors->get($context = ListContext::INTERACTIVE)) {
                throw new FlareException("List context '{$context}' has no projector registered.");
            }

            $projection = $projector->project($listContext, $listDefinition);

            /*
            $listView = $this->listViewBuilderFactory->create()
                ->setContentContext($contentContext)
                ->setListDefinition($listDefinition)
                ->setPaginatorConfig($paginatorConfig)
                ->setSortDescriptor(null)
                ->build();
            */
        }
        catch (FlareException $e)
        {
            $this->logger->error(\sprintf('%s (tl_content.id=%s, tl_flare_list.id=%s)', $e->getMessage(), $contentModel->id, $listModel->id),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e]);

            return $this->getErrorResponse($e);
        }

        $this->responseTagger->addTags(['contao.db.' . $listModel->dc]);

        $event = $this->eventDispatcher->dispatch(
            new ListViewRenderEvent(
                listContext: $listContext,
                listModel: $listModel,
                template: $template,
            )
        );

        $data = $template->getData();
        $data['flare'] ??= $event->getListView();
        $template->setData($data);

        return $template->getResponse();
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        try {
            /** @var ?ListModel $listModel */
            $listModel = $model->getRelated(ContentContainer::FIELD_LIST);
        } catch (\Exception $e) {
            return new Response($e->getMessage());
        }

        if (($headline = StringUtil::deserialize($model->headline, true)) && isset($headline['value'])) {
            $unit = ($headline['unit'] ?? null) ?: 'h2';
            $hl = \sprintf('<%s>%s</%s>', $unit, $headline['value'], $unit);
        }

        return new Response(\sprintf(
            '%s%s <span class="tl_gray">[%s, %s]</span>',
            $hl ?? '',
            $listModel->title,
            $this->translationManager->listModel($listModel),
            $listModel->dc
        ));
    }

    /**
     * Get the sort descriptor for a given list model.
     *
     * @return SortDescriptor|null The sort descriptor, or null if none is found.
     *
     * @throws FlareException bubbling from {@see SortDescriptor::fromSettings()}
     */
    public function getSortDescriptor(ListModel $listModel): ?SortDescriptor
    {
        if (!$listModel->sortSettings) {
            return null;
        }

        $sortSettings = StringUtil::deserialize($listModel->sortSettings);
        if (!$sortSettings || !\is_array($sortSettings)) {
            return null;
        }

        return SortDescriptor::fromSettings($sortSettings);
    }
}