<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Message;
use HeimrichHannot\FlareBundle\Contract\FilterElement\InScopeContract;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCallback(FilterContainer::TABLE_NAME, 'config.onload')]
readonly class ConfigOnLoadCallback
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private TranslatorInterface $translator,
    ) {}

    /**
     * If the filter is limited in scope, we need to show the user an information message with the available scopes.
     *
     * @param DataContainer|null $dc
     * @return void
     */
    public function __invoke(?DataContainer $dc = null): void
    {
        if (!$filterModel = DcaHelper::modelOf($dc)) {
            return;
        }

        if (!$filterModel instanceof FilterModel) {
            return;
        }

        if (!$descriptor = $this->filterElementRegistry->get($filterModel->type)) {
            return;
        }

        $this->scopeMessage($descriptor);
    }

    public function scopeMessage(FilterElementDescriptor $descriptor): void
    {
        if ($descriptor->getService() instanceof InScopeContract)
            // If the filter is limited in scope by a dynamic service implementation, make the user aware of this.
        {
            Message::addInfo($this->translator->trans('filter.limited_scope.dynamic', [], 'flare'));
            return;
        }

        if (\is_null($descriptor->getScopes()))
            // If the filter is not limited in scope, we don't need to show the information message.
        {
            return;
        }

        if (!$descriptor->getScopes())
            // If the filter is limited in scope, but the scope is empty, signal a misconfiguration.
        {
            Message::addInfo($this->translator->trans('filter.limited_scope.disqualified', [], 'flare'));
            return;
        }

        $msgKey = (\count($descriptor->getScopes()) === 1)
            ? 'filter.limited_scope.single'
            : 'filter.limited_scope.multiple';

        Message::addInfo($this->translator->trans($msgKey, [
            '%scopes%' => \implode(', ', \array_map(
                fn (string $scope): string => $this->translator->trans('filter.scope.' . $scope, [], 'flare'),
                $descriptor->getScopes(),
            )),
        ], 'flare'));
    }
}