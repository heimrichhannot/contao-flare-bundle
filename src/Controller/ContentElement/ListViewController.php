<?php

namespace HeimrichHannot\FlareBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\StringUtil;
use Contao\Template;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\ListView\Builder\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsContentElement(ListViewController::TYPE, category: 'includes', template: 'content_element/flare_listview')]
class ListViewController extends AbstractContentElementController
{
    public const TYPE = 'flare_listview';

    public function __construct(
        private readonly ListViewBuilderFactory $listViewBuilderFactory,
        private readonly KernelInterface        $kernel,
        private readonly LoggerInterface        $logger,
        private readonly ScopeMatcher           $scopeMatcher,
        private readonly TranslatorInterface    $translator,
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

    /**
     * @throws \Exception
     */
    protected function getErrorResponse(?\Exception $e = null): Response
    {
        if (isset($e) && $this->kernel->getEnvironment() === 'dev') {
            throw $e;
        }
        /** @noinspection PhpTranslationKeyInspection, PhpTranslationDomainInspection */
        $msg = $this->translator->trans('ERR.flare.listview.malconfigured', [], 'contao_modules');
        return new Response($msg);
    }

    /**
     * @throws \Exception
     */
    protected function getFrontendResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
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

            return $this->getErrorResponse($e);
        }

        try
        {
            $listViewDto = $this->listViewBuilderFactory
                ->create()
                ->setListModel($listModel)
                ->setFormName($model->flare_formName ?: null)
                ->build();
        }
        catch (FilterException $e)
        {
            $this->logger->error(\sprintf('%s (tl_content.id=%s, tl_flare_list.id=%s)', $e->getMessage(), $model->id, $listModel->id),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR), 'exception' => $e]);

            return $this->getErrorResponse($e);
        }

        $data = ['flare' => $listViewDto];

        $template->setData($data + $template->getData());

        return $template->getResponse();
    }

    protected function getBackendResponse(Template $template, ContentModel $model, Request $request): ?Response
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

        return new Response(($hl ?? '') . \sprintf('%s <span class="tl_gray">[%s]</span>', $listModel->title, $listModel->type));
    }
}