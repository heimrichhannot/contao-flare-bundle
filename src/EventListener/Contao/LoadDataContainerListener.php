<?php

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\Input;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackContainerInterface;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

#[AsHook('loadDataContainer')]
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
            'tl_flare_filter' => 'filter.',
            'tl_flare_list' => 'list.',
        };

        $callbacks = $this->registry->get($prefix . $model->type) ?? [];

        if (empty($callbacks)) {
            return;
        }

        $container = match ($table) {
            'tl_flare_filter' => $this->filterContainer,
            'tl_flare_list' => $this->listContainer,
        };

        if (!\is_subclass_of($container, FlareCallbackContainerInterface::class)) {
            return;
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

            if (!empty($callbacks[$target = "fields.$field.options"]))
                // bind options callback
            {
                $definition['options_callback'] = static function (?DataContainer $dc) use ($container, $target) {
                    return $container->handleFieldOptions($dc, $target);
                };
            }

            if (!empty($callbacks[$target = "fields.$field.load"]))
                // bind load callback
            {
                if (!\is_array($definition['load_callback'] ?? null)) {
                    $definition['load_callback'] = [];
                }

                $definition['load_callback'][] = static function (mixed $value, ?DataContainer $dc) use ($container, $target) {
                    return $container->handleLoadField($value, $dc, $target);
                };
            }

            if (!empty($callbacks[$target = "fields.$field.save"]))
                // bind save callback
            {
                if (!\is_array($definition['save_callback'] ?? null)) {
                    $definition['save_callback'] = [];
                }

                $definition['save_callback'][] = static function (mixed $value, ?DataContainer $dc) use ($container, $target, $model) {
                    return $container->handleSaveField($value, $dc, $target);
                };
            }
        }
    }
}