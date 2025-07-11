<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Message;
use HeimrichHannot\FlareBundle\Contract\FilterElement\InScopeContract;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
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
        if (!$dc || !$dc->id) {
            return;
        }

        if (!$model = DcaHelper::modelOf($dc)) {
            return;
        }

        if (!$model instanceof FilterModel) {
            return;
        }

        if (!$config = $this->filterElementRegistry->get($model->type)) {
            return;
        }

        if ($config->getService() instanceof InScopeContract)
        {
            Message::addInfo($this->translator->trans('filter.limited_scope.dynamic', [], 'flare'));
            return;
        }

        // If the filter is not limited in scope, we don't need to show the information message.
        if (\is_null($config->getScopes()))
        {
            return;
        }

        if (empty($config->getScopes()))
        {
            Message::addInfo($this->translator->trans('filter.limited_scope.disqualified', [], 'flare'));
            return;
        }

        if (\count($config->getScopes()) === 1)
        {
            Message::addInfo($this->translator->trans('filter.limited_scope.single', [
                '%scope%' => $this->translator->trans('filter.scope.' . $config->getScopes()[0], [], 'flare'),
            ], 'flare'));
            return;
        }

        Message::addInfo($this->translator->trans('filter.limited_scope.multiple', [
            '%scopes%' => implode(', ', \array_map(
                fn (string $scope) => $this->translator->trans('filter.scope.' . $scope, [], 'flare'),
                $config->getScopes()
            )),
        ], 'flare'));
    }
}