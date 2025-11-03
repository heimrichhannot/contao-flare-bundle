<?php

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\Input;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\DataContainer\FlareCallbackContainerInterface;
use HeimrichHannot\FlareBundle\Registry\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

#[AsHook('loadDataContainer', priority: -100)]
readonly class LoadDataContainerListener
{
    public function __construct(
        private FilterContainer $filterContainer,
        private FlareCallbackRegistry $registry,
        private ListContainer $listContainer,
    ) {}

    /**
     * @throws \Exception
     */
    public function __invoke(string $table): void
    {
        if ($table !== 'tl_flare_filter' && $table !== 'tl_flare_list') {
            return;
        }

        if (!isset($GLOBALS['TL_DCA'][$table]['fields'])) {
            return;
        }

        if (!$id = Input::get('id')) {
            return;
        }

        $model = match ($table) {
            'tl_flare_filter' => FilterModel::findByPk($id),
            'tl_flare_list' => ListModel::findByPk($id),
        };

        if (!$model || !$model->type) {
            return;
        }

        $prefix = match ($table) {
            'tl_flare_filter' => 'filter',
            'tl_flare_list' => 'list',
        };

        if (!$callbacks = $this->registry->getNamespace("{$prefix}.{$model->type}")) {
            return;
        }

        $container = match ($table) {
            'tl_flare_filter' => $this->filterContainer,
            'tl_flare_list' => $this->listContainer,
        };

        if (!\is_subclass_of($container, FlareCallbackContainerInterface::class)) {
            return;
        }

        /** @mago-expect lint:no-empty This is the most straightforward way to check if the callback should be bound. */
        if (!empty($callbacks[$target = 'config.onload']))
            // bind onload callback
        {
            $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] =
                static fn (DataContainer $dc): null => $container->handleConfigOnLoad($dc, $target);
        }

        $exclude = \array_fill_keys(['id', 'pid', 'tstamp', 'sorting', 'type', 'published', 'intrinsic'], true);

        $refFields = &$GLOBALS['TL_DCA'][$table]['fields'];

        foreach ($refFields as $field => &$definition)
        {
            if ($exclude[$field] ?? false) {
                continue;
            }

            // Always pass the target to the handler method,
            //   to ensure that cloning of fields is possible
            //   without interfering with the callback execution.
            // This is required for the group widget, for example.

            $eventPrefix = "flare.{$prefix}.fields.{$field}";

            $definition['options_callback'] =
                static fn (?DataContainer $dc): array => $container->handleFieldOptions($dc, "$eventPrefix.options");

            if (!empty($callbacks[$target = "fields.{$field}.load"]))
                // bind load callback
            {
                if (!\is_array($definition['load_callback'] ?? null)) {
                    $definition['load_callback'] = [];
                }

                $definition['load_callback'][] =
                    static fn (mixed $value, ?DataContainer $dc): mixed => $container->handleLoadField($value, $dc, $target);
            }

            if (!empty($callbacks[$target = "fields.{$field}.save"]))
                // bind save callback
            {
                if (!\is_array($definition['save_callback'] ?? null)) {
                    $definition['save_callback'] = [];
                }

                $definition['save_callback'][] =
                    static fn (mixed $value, ?DataContainer $dc): mixed => $container->handleSaveField($value, $dc, $target);
            }
        }
    }
}