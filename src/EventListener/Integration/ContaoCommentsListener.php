<?php

namespace HeimrichHannot\FlareBundle\EventListener\Integration;

use Contao\Comments;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\FrontendTemplate;
use Contao\NewsArchiveModel;
use Contao\NewsModel;
use Contao\UserModel;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Event\PaletteEvent;
use HeimrichHannot\FlareBundle\Event\ReaderRenderEvent;
use HeimrichHannot\FlareBundle\Model\ContentModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ContaoCommentsListener
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    /**
     * Attach comments to the reader content element template data.
     */
    #[AsEventListener]
    public function onReaderBuilt(ReaderRenderEvent $event): void
    {
        $list = $event->getListSpecification();
        if (!$list->comments_enabled) {
            return;
        }

        if (!\class_exists(NewsModel::class)
            || !\class_exists(NewsArchiveModel::class)
            || !\class_exists(Comments::class)) {
            return;
        }

        /** @var NewsModel $newsModel */
        $newsModel = $event->getDisplayModel();
        if (!$newsModel instanceof NewsModel) {
            return;
        }

        $archiveModel = $newsModel->getRelated('pid');
        if (!$archiveModel instanceof NewsArchiveModel) {
            return;
        }

        if (!$archiveModel->allowComments) {
            return;
        }

        $notifies = [];

        if ($list->comments_sendNativeEmails)
        {
            if ($archiveModel->notify !== 'notify_author'
                && isset($GLOBALS['TL_ADMIN_EMAIL']))
            {
                $notifies[] = $GLOBALS['TL_ADMIN_EMAIL'];
            }

            if ($archiveModel->notify !== 'notify_admin'
                && $authorEmail = (UserModel::findById($newsModel->author ?: 0)?->email))
            {
                $notifies[] = $authorEmail;
            }
        }

        $config = new \stdClass();
        $config->perPage = $archiveModel->perPage;
        $config->order = $archiveModel->sortOrder;
        $config->template = $event->getContentModel()->com_template ?: null;
        $config->requireLogin = $archiveModel->requireLogin;
        $config->disableCaptcha = $archiveModel->disableCaptcha;
        $config->bbcode = $archiveModel->bbcode;
        $config->moderate = $archiveModel->moderate;

        $commentsTemplate = new FrontendTemplate();

        (new Comments())->addCommentsToTemplate(
            objTemplate: $commentsTemplate,
            objConfig: $config,
            strSource: $newsModel::getTable(),
            intParent: $newsModel->id,
            varNotifies: $notifies,
        );

        if ($commentsData = $commentsTemplate->getData())
        {
            $event->set('comments', $commentsData);
        }
    }

    /**
     * Attach the comments_enabled field to the flare_news palette.
     */
    #[AsEventListener('flare.list.flare_news.palette')]
    public function onListPalette(PaletteEvent $event): void
    {
        $pm = PaletteManipulator::create()
            ->addLegend('comments_legend')
            ->addField('comments_enabled', 'comments_legend', PaletteManipulator::POSITION_APPEND);

        if ($event->getPaletteConfig()->getListModel()->comments_enabled) {
            $pm->addField('comments_sendNativeEmails', 'comments_legend', PaletteManipulator::POSITION_APPEND);
        }

        $event->setPalette($pm->applyToString($event->getPalette()));
    }

    /**
     * Attach the com_template field to the flare_reader content element palette.
     */
    #[AsCallback(table: 'tl_content', target: 'config.onload')]
    public function onFlareReaderLoad(?DataContainer $dc = null): void
    {
        if ($dc === null || !$dc->id || $this->requestStack->getCurrentRequest()->query->get('act') !== 'edit') {
            return;
        }

        if (!($element = ContentModel::findById($dc->id)) || $element->type !== 'flare_reader') {
            return;
        }

        if (!ListModel::findById($element->{ContentContainer::FIELD_LIST} ?? 0)?->comments_enabled) {
            return;
        }

        $palettes = &$GLOBALS['TL_DCA']['tl_content']['palettes'];

        $palettes['flare_reader'] = PaletteManipulator::create()
            ->addField('com_template', 'template_legend', PaletteManipulator::POSITION_APPEND)
            ->applyToString($palettes['flare_reader']);
    }
}