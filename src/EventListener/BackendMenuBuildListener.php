<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use HeimrichHannot\FlareBundle\Contao\BackendModule;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD)]
readonly class BackendMenuBuildListener
{
    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();
        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        if (!$groupNode = $tree->getChild(BackendModule::CATEGORY)) {
            return;
        }

        if (!$flareNode = $groupNode->getChild(BackendModule::NAME)) {
            return;
        }

        if (\str_contains($label = $flareNode->getLabel(), '(FLARE)'))
        {
            $flareNode->setLabel(\str_replace('(FLARE)', '', $label));
            $flareNode->setLinkAttribute('data-flare-suffix', 'FLARE');
        }
    }
}