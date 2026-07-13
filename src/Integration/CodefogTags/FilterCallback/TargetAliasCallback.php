<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterCallback;

use HeimrichHannot\FlareBundle\Event\ElementDcaEvent;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsChoiceFilterElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsSearchElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry\CfgTagsJoinsRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Restricts the targetAlias options of the Codefog tags filter elements to the
 * active tags relations of the edited list.
 */
#[AsEventListener('flare.filter_element.' . CodefogTagsChoiceFilterElement::TYPE . '.dca')]
#[AsEventListener('flare.filter_element.' . CodefogTagsSearchElement::TYPE . '.dca')]
readonly class TargetAliasCallback
{
    public function __construct(
        private CfgTagsJoinsRegistry $joinsRegistry,
    ) {}

    public function __invoke(ElementDcaEvent $event): void
    {
        if (!$context = $event->context->getExecutionContext()) {
            return;
        }

        $event->dca->field('targetAlias')->options(function () use ($context): array {
            $activeTagsAliases = \array_intersect_key(
                $this->joinsRegistry->all(),
                \array_flip($context->tableAliasRegistry->getAliases()),
            );

            $options = [];

            foreach ($activeTagsAliases as $alias => $config) {
                $options[$alias] = "{$alias} [tl_cfg_tag]";
            }

            return $options;
        });
    }
}
