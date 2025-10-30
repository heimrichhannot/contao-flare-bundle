<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use Composer\InstalledVersions;
use HeimrichHannot\FlareBundle\Event\CreateListViewBuilderEvent;
use HeimrichHannot\FlareBundle\ListView\Resolver\MultilingualListViewResolver;
use HeimrichHannot\FlareBundle\Manager\ListViewManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class MultilingualListener
{

    public function __construct(
        private readonly ListViewManager $listViewManager,
    ) {
    }

    #[AsEventListener]
    public function onCreateListViewBuilderEvent(CreateListViewBuilderEvent $event): void
    {
        if (!InstalledVersions::isInstalled('terminal42/contao-changelanguage')) {
            return;
        }

        $event->setResolver(new MultilingualListViewResolver($this->listViewManager));
    }
}