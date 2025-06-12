<?php

namespace HeimrichHannot\FlareBundle\EventListener;

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
use HeimrichHannot\FlareBundle\Event\ReaderBuiltEvent;
use HeimrichHannot\FlareBundle\Model\ContentModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class CommentsListener
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    /**
     * Attach comments to the reader content element template data.
     */
    #[AsEventListener('huh.flare.reader.built')]
    public function onReaderBuilt(ReaderBuiltEvent $event): void
    {
        /** @var ListModel $listModel */
        $listModel = $event->getListModel();
        if (!$listModel->comments_enabled || !\class_exists(Comments::class)) {
            return;
        }

        /** @var NewsModel $newsModel */
        if (!$newsModel = $event->getDisplayModel()) {
            return;
        }

        /** @var NewsArchiveModel $archiveModel */
        if (!$archiveModel = $newsModel->getRelated('pid')) {
            return;
        }

        if (!$archiveModel->allowComments) {
            return;
        }

        $notifies = [];

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

        $config = new \stdClass();
        $config->perPage = $archiveModel->perPage;
        $config->order = $archiveModel->sortOrder;
        $config->template = $event->getContentModel()->com_template ?: null;
        $config->requireLogin = $archiveModel->requireLogin;
        $config->disableCaptcha = $archiveModel->disableCaptcha;
        $config->bbcode = $archiveModel->bbcode;
        $config->moderate = $archiveModel->moderate;

        $template = new FrontendTemplate();

        (new Comments())->addCommentsToTemplate(
            objTemplate: $template,
            objConfig: $config,
            strSource: $newsModel->getTable(),
            intParent: $newsModel->id,
            varNotifies: $notifies,
        );

        if ($commentsData = $template->getData())
        {
            $data = $event->getData();
            $data['comments'] = $commentsData;
            $event->setData($data);
        }
    }

    /**
     * Attach the comments_enabled field to the flare_news palette.
     */
    #[AsEventListener('huh.flare.list.palette')]
    public function onListPalette(PaletteEvent $event): void
    {
        if ($event->getPaletteConfig()->getAlias() !== 'flare_news') {
            return;
        }

        if (!\class_exists(Comments::class)) {
            return;
        }

        $event->setPalette(PaletteManipulator::create()
            ->addLegend('comments_legend')
            ->addField('comments_enabled','comments_legend', PaletteManipulator::POSITION_APPEND)
            ->applyToString($event->getPalette()),
        );
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

        if (!\class_exists(Comments::class)) {
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